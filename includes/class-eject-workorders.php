<?php
/*
 * File: includes/class-eject-workorders.php
 * Description: Generates Work Order PDFs for a run of POs.
 */

if (!defined('ABSPATH')) exit;

require_once EJECT_DIR . 'includes/lib/tcpdf.php';

class Eject_Workorders {
    public static function register(): void {
        add_action('admin_post_eject_print_workorders', [self::class, 'handle_print']);
    }

    public static function handle_print(): void {
        if (!current_user_can('manage_woocommerce')) wp_die('Permission denied.');
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
        if (!wp_verify_nonce($nonce, 'eject_print_workorders')) wp_die('Bad nonce.');

        $po_id  = isset($_POST['po_id']) ? absint($_POST['po_id']) : 0;
        if (!$po_id) wp_die('Missing PO ID.');

        $run_id = get_post_meta($po_id, '_run_id', true);
        if (!$run_id) $run_id = 'po-' . $po_id;

        $data = self::collect_run_data($run_id);
        if (empty($data['orders'])) {
            wp_die('No orders found for this run.');
        }

        self::render_pdf($data, $run_id);
        exit;
    }

    /** Collect orders + items for a run id */
    private static function collect_run_data(string $run_id): array {
        $pos = get_posts([
            'post_type'   => 'eject_po',
            'post_status' => ['publish','draft'],
            'numberposts' => -1,
            'meta_key'    => '_run_id',
            'meta_value'  => $run_id,
            'fields'      => 'ids',
        ]);

        if (empty($pos)) return ['orders' => [], 'vendors' => [], 'po_ids' => []];

        $vendors = [];
        $order_ids = [];
        $po_numbers = [];
        $po_dates   = [];
        foreach ($pos as $pid) {
            $vendor = get_post_meta($pid, '_vendor_id', true);
            if ($vendor !== '') $vendors[$vendor] = true;
            $oids = (array) get_post_meta($pid, '_order_ids', true);
            foreach ($oids as $oid) {
                if ($oid) $order_ids[$oid] = true;
            }
            $po_no = get_post_meta($pid, '_po_number', true);
            $po_numbers[] = $po_no ?: $pid;
            $po_date = get_post_meta($pid, '_po_date', true);
            if ($po_date) $po_dates[] = $po_date;
        }

        $orders = [];
        $vendor_list = array_keys($vendors);
        foreach (array_keys($order_ids) as $oid) {
            $order = wc_get_order($oid);
            if (!$order) continue;
            $lines = Eject_Service::lines_for_order($order, $vendor_list);
            if (empty($lines)) continue;
            $orders[] = [
                'order_id'      => $oid,
                'order_number'  => $order->get_order_number(),
                'items'         => self::group_lines($lines),
                'media'         => self::collect_media($order),
                'instructions'  => self::collect_instructions($order),
            ];
        }

        return [
            'orders'     => $orders,
            'vendors'    => $vendor_list,
            'po_ids'     => $pos,
            'po_numbers' => array_values(array_unique(array_filter($po_numbers))),
            'po_dates'   => array_values(array_unique(array_filter($po_dates))),
        ];
    }

    /** Group lines to VendorItem -> {product, colors => Size => Qty} */
    private static function group_lines(array $lines): array {
        $tree = [];
        foreach ($lines as $line) {
            $code  = $line['vendor_item_code'] ?: $line['product_name'];
            $color = $line['color'] ?: 'N/A';
            $size  = $line['size'] ?: 'N/A';
            $qty   = max(1, (int) $line['qty']);
            if (!isset($tree[$code])) {
                $tree[$code] = [
                    'product' => $line['product_name'],
                    'colors'  => [],
                ];
            }
            if (!isset($tree[$code]['colors'][$color])) $tree[$code]['colors'][$color] = [];
            if (!isset($tree[$code]['colors'][$color][$size])) $tree[$code]['colors'][$color][$size] = 0;
            $tree[$code]['colors'][$color][$size] += $qty;
        }

        $size_order = ['NB','06M','12M','18M','24M','XS','S','M','L','XL','2XL','3XL','4XL','5XL'];
        foreach ($tree as $code => $entry) {
            foreach ($entry['colors'] as $color => $sizes) {
                uksort($sizes, function($a, $b) use ($size_order) {
                    $a_i = array_search(strtoupper(trim($a)), $size_order, true);
                    $b_i = array_search(strtoupper(trim($b)), $size_order, true);
                    $a_i = ($a_i === false) ? 999 : $a_i;
                    $b_i = ($b_i === false) ? 999 : $b_i;
                    if ($a_i === $b_i) return strcmp($a, $b);
                    return $a_i <=> $b_i;
                });
                $tree[$code]['colors'][$color] = $sizes;
            }
        }

        return $tree;
    }

    /** Collect special instructions from line items */
    private static function collect_instructions(WC_Order $order): array {
        $out = [];
        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            $val = $item->get_meta('Special Instructions for production', true);
            if (!$val && $product) {
                $val = $product->get_meta('Special Instructions for production', true);
            }
            if ($val) $out[] = trim((string) $val);
        }
        return array_values(array_unique(array_filter($out)));
    }

    /** Collect media (mockup + original art) per product on the order */
    private static function collect_media(WC_Order $order): array {
        $out = [];
        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            $name = $item->get_name();

            $mockup = self::extract_media_url($item, $product, [
                'mockup', 'mockup_url', 'mockup image', 'mockup_image', 'mockup preview',
                'mockup link', 'mockup file',
            ]);
            $art = self::extract_media_url($item, $product, [
                'original_art', 'original art', 'art', 'artwork', 'art_url', 'art file', 'art_file',
            ]);

            if (!$mockup && !$art) continue;
            $out[] = [
                'product' => $name,
                'mockup'  => $mockup,
                'art'     => $art,
            ];
        }

        // Deduplicate by product+urls
        $seen = [];
        $dedup = [];
        foreach ($out as $rec) {
            $key = md5(strtolower($rec['product']).'|'.$rec['mockup'].'|'.$rec['art']);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $dedup[] = $rec;
        }
        return $dedup;
    }

    private static function extract_media_url(WC_Order_Item_Product $item, ?WC_Product $product, array $keys): string {
        // Order item meta (direct match)
        foreach ($keys as $k) {
            $val = $item->get_meta($k, true);
            $url = self::normalize_media_value($val);
            if ($url) return $url;
        }
        // Order item meta contains keys; scan all meta
        foreach ($item->get_meta_data() as $meta) {
            $data = $meta->get_data();
            $mk = strtolower((string) ($data['key'] ?? ''));
            foreach ($keys as $k) {
                if (strpos($mk, strtolower($k)) !== false) {
                    $url = self::normalize_media_value($data['value'] ?? '');
                    if ($url) return $url;
                }
            }
        }
        // Product meta
        if ($product) {
            foreach ($keys as $k) {
                $val = $product->get_meta($k, true);
                $url = self::normalize_media_value($val);
                if ($url) return $url;
            }
        }
        return '';
    }

    private static function normalize_media_value($val): string {
        if (is_array($val)) {
            if (isset($val['url'])) return self::clean_url($val['url']);
            if (isset($val[0])) return self::clean_url($val[0]);
        }
        if (is_numeric($val)) {
            $url = wp_get_attachment_url((int)$val);
            if ($url) return self::clean_url($url);
        }
        if (is_string($val) && $val !== '') {
            return self::clean_url($val);
        }
        return '';
    }

    private static function clean_url(string $url): string {
        $url = trim($url);
        if ($url === '') return '';
        // Basic validation: only http/https
        if (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) {
            return $url;
        }
        return '';
    }

    private static function render_pdf(array $data, string $run_id): void {
        $margin = 6.35; // 1/4"
        $pdf = new TCPDF('P','mm','LETTER', true, 'UTF-8', false);
        $pdf->SetCreator('Eject');
        $pdf->SetAuthor('Eject');
        $pdf->SetTitle('Eject Work Orders');
        $pdf->SetMargins($margin, $margin, $margin);
        $pdf->SetAutoPageBreak(true, $margin);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $logo      = self::get_logo_url();
        $po_label  = !empty($data['po_numbers']) ? implode(', ', $data['po_numbers']) : '';
        $po_dates  = !empty($data['po_dates']) ? implode(', ', $data['po_dates']) : date_i18n('Y-m-d');

        foreach ($data['orders'] as $order) {
            $start_page = $pdf->getNumPages() + 1;
            $pdf->AddPage();

            self::render_header($pdf, $order['order_number'], $po_label, $po_dates, $logo);

            // divider
            $x1 = $pdf->GetX();
            $x2 = $pdf->getPageWidth() - $pdf->getMargins()['right'];
            $y  = $pdf->GetY();
            $pdf->Line($x1, $y, $x2, $y);
            $pdf->Ln(4);

            self::render_items_table($pdf, $order['items']);

            if (!empty($order['media'])) {
                $pdf->Ln(6);
                self::render_media_table($pdf, $order['media']);
            }

            if (!empty($order['instructions'])) {
                $pdf->Ln(6);
                $pdf->SetFont('dejavusans','B',11);
                $pdf->Cell(0,6,'Special Instructions for production',0,1,'L');
                $pdf->SetFont('dejavusans','',9);
                foreach ($order['instructions'] as $inst) {
                    $pdf->MultiCell(0,5,'- '.$inst,0,'L',false,1);
                }
            }

            // Footer (per order page counts)
            $end_page    = $pdf->getNumPages();
            $total_pages = $end_page - $start_page + 1;
            $current     = $pdf->getPage();
            for ($p = $start_page, $i = 1; $p <= $end_page; $p++, $i++) {
                $pdf->setPage($p);
                $pdf->SetY(-10);
                $pdf->SetFont('dejavusans','',9);
                $pdf->Cell(0,6,'Work Order #'.$order['order_number'].'  Page '.$i.' of '.$total_pages,0,0,'C');
            }
            $pdf->setPage($current);
        }

        $content = $pdf->Output('work-orders-'.$run_id.'.pdf', 'S');
        if (function_exists('ob_get_length') && ob_get_length()) @ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="work-orders-'.$run_id.'.pdf"');
        header('Content-Length: '.strlen($content));
        echo $content;
    }

    private static function render_items_table(TCPDF $pdf, array $items): void {
        $col = [70, 30, 30, 20, 15]; // Item, Garment, Color, Size, Qty
        $pdf->SetFont('dejavusans','B',11);
        $pdf->Cell($col[0],7,'Item:',0,0,'L');
        $pdf->Cell($col[1],7,'Garment:',0,0,'L');
        $pdf->Cell($col[2],7,'Color:',0,0,'L');
        $pdf->Cell($col[3],7,'Size:',0,0,'L');
        $pdf->Cell($col[4],7,'Qty:',0,1,'L');

        $pdf->SetFont('dejavusans','',9);
        foreach ($items as $code => $entry) {
            $product = $entry['product'] ?? '';
            $colors  = $entry['colors'] ?? [];
            foreach ($colors as $color => $sizes) {
                foreach ($sizes as $size => $qty) {
                    $item_text = $product !== '' ? $product : $code;
                    $h1 = $pdf->getStringHeight($col[0], $item_text);
                    $h  = max($h1, 6);
                    $pdf->MultiCell($col[0], $h, $item_text, 0, 'L', false, 0);
                    $pdf->Cell($col[1], $h, $code, 0, 0, 'L');
                    $pdf->Cell($col[2], $h, $color, 0, 0, 'L');
                    $pdf->Cell($col[3], $h, $size, 0, 0, 'L');
                    $pdf->Cell($col[4], $h, (string)$qty, 0, 1, 'L');
                }
            }
        }
    }

    private static function render_media_table(TCPDF $pdf, array $media): void {
        $pdf->SetFont('dejavusans','B',11);
        $pdf->Cell(90,6,'Mockup:',0,0,'L');
        $pdf->Cell(90,6,'Original Art:',0,1,'L');

        foreach ($media as $m) {
            $startY = $pdf->GetY();
            $maxH = 0;

            if (!empty($m['mockup'])) {
                $h = self::render_image($pdf, $m['mockup'], 50.8); // 2"
                $maxH = max($maxH, $h);
            }

            if (!empty($m['art'])) {
                $pdf->SetXY($pdf->GetX()+90, $startY);
                $h = self::render_image($pdf, $m['art'], 50.8); // 2"
                $maxH = max($maxH, $h);
            }

            $pdf->Ln(($maxH > 0 ? $maxH : 6) + 4);
        }
    }

    private static function render_header(TCPDF $pdf, string $order_number, string $po_label, string $date_label, string $logo_url): void {
        $margins = $pdf->getMargins();
        $qr_size = 25.4; // 1"
        $logo_w  = 25.4; // 1"

        $qr_url = 'https://thebeartraxs.com/traxs?ordernumber='.urlencode($order_number);
        $style = ['border'=>0,'padding'=>0,'fgcolor'=>[0,0,0],'bgcolor'=>false];
        $qr_x = $pdf->getPageWidth() - $margins['right'] - $qr_size;
        $qr_y = $margins['top'];
        $pdf->write2DBarcode($qr_url, 'QRCODE,H', $qr_x, $qr_y, $qr_size, $qr_size, $style, 'N');

        $pdf->SetFont('dejavusans','B',12);
        $pdf->SetXY($margins['left'], $margins['top']);
        $pdf->MultiCell(0,5,"The Bear Traxs\nWork Order #".$order_number,0,'L',false,1);
        $pdf->SetFont('dejavusans','',10);
        if ($po_label !== '') {
            $pdf->MultiCell(0,5,'Vendor POs: '.$po_label,0,'L',false,1);
        }
        if ($date_label !== '') {
            $pdf->MultiCell(0,5,'Date: '.$date_label,0,'L',false,1);
        }

        if ($logo_url) {
            $logo_x = ($pdf->getPageWidth() - $logo_w) / 2;
            $logo_y = $margins['top'];
            $orig_x = $pdf->GetX();
            $orig_y = $pdf->GetY();
            $pdf->SetXY($logo_x, $logo_y);
            self::render_image($pdf, $logo_url, $logo_w);
            $pdf->SetXY($orig_x, max($orig_y, $logo_y + $logo_w + 2));
        }

        $pdf->SetY($margins['top'] + $qr_size + 4);
    }

    private static function get_logo_url(): string {
        $logo_id = get_theme_mod('custom_logo');
        if ($logo_id) {
            $src = wp_get_attachment_image_src($logo_id, 'full');
            if ($src && !empty($src[0])) return $src[0];
        }
        return '';
    }

    private static function render_image(TCPDF $pdf, string $url, float $width = 60): float {
        $path = $url;
        $temp = null;
        $size = null;

        // If remote URL, fetch and store locally; try to convert unknown formats to PNG
        if (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) {
            $resp = wp_remote_get($url, ['timeout' => 10]);
            if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                $body = wp_remote_retrieve_body($resp);
                if ($body !== '') {
                    $type = wp_remote_retrieve_header($resp, 'content-type');
                    if (is_array($type)) $type = reset($type);
                    $ext = 'jpg';
                    if (is_string($type)) {
                        if (stripos($type, 'png') !== false) $ext = 'png';
                        elseif (stripos($type, 'gif') !== false) $ext = 'gif';
                        elseif (stripos($type, 'jpeg') !== false) $ext = 'jpg';
                    }
                    $temp = tempnam(sys_get_temp_dir(), 'eject_img_').'.'.$ext;
                    file_put_contents($temp, $body);
                    $path = $temp;

                    $size = @getimagesize($path);
                    if (!$size) {
                        $img = @imagecreatefromstring($body);
                        if ($img) {
                            $temp = tempnam(sys_get_temp_dir(), 'eject_img_').'.png';
                            imagepng($img, $temp);
                            imagedestroy($img);
                            $path = $temp;
                            $size = @getimagesize($path);
                        }
                    }
                }
            }
        } else {
            $size = @getimagesize($path);
        }

        $height = 0.0;
        if ($size && isset($size[0], $size[1]) && $size[0] > 0) {
            $height = $width * ($size[1] / $size[0]);
        }

        try {
            if ($size) {
                $pdf->Image($path, '', '', $width, 0, '', '', 'T', false, 300, '', false, false, 0, true);
            }
        } catch (\Throwable $e) {
            // ignore failures silently (no URLs printed per request)
            $height = 0.0;
        }

        if ($temp && file_exists($temp)) {
            @unlink($temp);
        }
        return $height;
    }
}
