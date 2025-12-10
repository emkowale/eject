<?php
/*
 * FPDF 1.85 (trimmed for plugin use)
 * Source: http://www.fpdf.org/
 * License: Freeware
 */

if (!defined('FPDF_VERSION')) define('FPDF_VERSION','1.85');
// Always use this bundled FPDF to avoid mixing versions

class FPDF
{
protected $page;               //current page number
protected $n;                  //current object number
protected $offsets;            //array of object offsets
protected $buffer;             //buffer holding in-memory PDF
protected $pages;              //array containing pages
protected $state;              //current document state
protected $compress;           //compression flag
protected $k;                  //scale factor (number of points in user unit)
protected $DefOrientation;     //default orientation
protected $CurOrientation;     //current orientation
protected $StdPageSizes;       //standard page sizes
protected $DefPageSize;        //default page size
protected $CurPageSize;        //current page size
protected $PageSizes;          //array storing non-default page sizes
protected $wPt,$hPt;           //dimensions of current page in points
protected $w,$h;               //dimensions of current page in user unit
protected $lMargin;            //left margin
protected $tMargin;            //top margin
protected $rMargin;            //right margin
protected $bMargin;            //page break margin
protected $cMargin;            //cell margin
protected $x,$y;               //current position in user unit
protected $lasth;              //height of last printed cell
protected $LineWidth;          //line width in user unit
protected $fontpath;           //path containing fonts
protected $CoreFonts;          //array of core font names
protected $fonts;              //array of used fonts
protected $FontFiles;          //array of font files
protected $encodings;          //array of encodings
protected $cmaps;              //array of ToUnicode CMaps
protected $FontFamily;         //current font family
protected $FontStyle;          //current font style
protected $underline;          //underlining flag
protected $CurrentFont;        //current font info
protected $FontSizePt;         //current font size in points
protected $FontSize;           //current font size in user unit
protected $DrawColor;          //commands for drawing color
protected $FillColor;          //commands for filling color
protected $TextColor;          //commands for text color
protected $ColorFlag;          //indicates whether fill and text colors are different
protected $WithAlpha;          //indicates whether alpha channel is used
protected $ws;                 //word spacing
protected $images;             //array of used images
protected $PageLinks;          //array of links in pages
protected $links;              //array of internal links
protected $AutoPageBreak;      //automatic page breaking
protected $PageBreakTrigger;   //threshold used to trigger page breaks
protected $InHeader;           //flag set when processing header
protected $InFooter;           //flag set when processing footer
protected $ZoomMode;           //zoom display mode
protected $LayoutMode;         //layout display mode
protected $title;              //title
protected $subject;            //subject
protected $author;             //author
protected $keywords;           //keywords
protected $creator;            //creator
protected $AliasNbPages;       //alias for total number of pages
protected $PDFVersion;         //PDF version number

function __construct($orientation='P', $unit='mm', $size='A4')
{
	$this->_dochecks();
	$this->page = 0;
	$this->n = 2;
	$this->buffer = '';
	$this->pages = array();
	$this->PageSizes = array();
	$this->state = 0;
	$this->fonts = array();
	$this->FontFiles = array();
	$this->encodings = array();
	$this->cmaps = array();
	$this->images = array();
	$this->links = array();
	$this->InHeader = false;
	$this->InFooter = false;
	$this->lasth = 0;
	$this->FontFamily = '';
	$this->FontStyle = '';
	$this->FontSizePt = 12;
	$this->underline = false;
	$this->DrawColor = '0 G';
	$this->FillColor = '0 g';
	$this->TextColor = '0 g';
	$this->ColorFlag = false;
	$this->WithAlpha = false;
	$this->ws = 0;

	//Standard fonts
	$this->CoreFonts = array('courier'=>'Courier','helvetica'=>'Helvetica','times'=>'Times','symbol'=>'Symbol','zapfdingbats'=>'ZapfDingbats');

	//Scale factor
	if($unit=='pt')
		$this->k = 1;
	elseif($unit=='mm')
		$this->k = 72/25.4;
	elseif($unit=='cm')
		$this->k = 72/2.54;
	elseif($unit=='in')
		$this->k = 72;
	else
		$this->Error('Incorrect unit: '.$unit);

	//Page sizes
	$this->StdPageSizes = array('a3'=>array(841.89,1190.55), 'a4'=>array(595.28,841.89), 'a5'=>array(420.94,595.28),
		'letter'=>array(612,792), 'legal'=>array(612,1008));
	$size = $this->_getpagesize($size);
	$this->DefPageSize = $size;
	$this->CurPageSize = $size;

	//Page orientation
	$orientation = strtolower($orientation);
	if($orientation=='p' || $orientation=='portrait')
	{
		$this->DefOrientation = 'P';
		$this->w = $size[0];
		$this->h = $size[1];
	}
	elseif($orientation=='l' || $orientation=='landscape')
	{
		$this->DefOrientation = 'L';
		$this->w = $size[1];
		$this->h = $size[0];
	}
	else
		$this->Error('Incorrect orientation: '.$orientation);
	$this->CurOrientation = $this->DefOrientation;

	$this->wPt = $this->w*$this->k;
	$this->hPt = $this->h*$this->k;

	//Page margins (1 cm)
	$margin = 28.35/$this->k;
	$this->SetMargins($margin,$margin);

	//Interior cell margin (1 mm)
	$this->cMargin = $margin/10;

	//Line width (0.2 mm)
	$this->LineWidth = .567/$this->k;

	//Automatic page break
	$this->SetAutoPageBreak(true,2*$margin);

	//Default display mode
	$this->SetDisplayMode('default');

	//Enable compression
	$this->SetCompression(true);

	//Set default PDF version number
	$this->PDFVersion = '1.3';
}

function SetMargins($left, $top, $right=null)
{
	$this->lMargin = $left;
	$this->tMargin = $top;
	if($right===null)
		$this->rMargin = $left;
	else
		$this->rMargin = $right;
}

function SetLeftMargin($margin)
{
	$this->lMargin = $margin;
	if($this->page>0 && $this->x<$margin)
		$this->x = $margin;
}

function SetTopMargin($margin)
{
	$this->tMargin = $margin;
}

function SetRightMargin($margin)
{
	$this->rMargin = $margin;
}

function SetAutoPageBreak($auto, $margin=0)
{
	$this->AutoPageBreak = $auto;
	$this->bMargin = $margin;
	$this->PageBreakTrigger = $this->h-$margin;
}

function SetDisplayMode($zoom, $layout='default')
{
	if($zoom=='fullpage' || $zoom=='fullwidth' || $zoom=='real' || $zoom=='default' || !is_string($zoom))
		$this->ZoomMode = $zoom;
	else
		$this->Error('Incorrect zoom display mode: '.$zoom);
	if($layout=='single' || $layout=='continuous' || $layout=='two' || $layout=='default')
		$this->LayoutMode = $layout;
	else
		$this->Error('Incorrect layout display mode: '.$layout);
}

function SetCompression($compress)
{
	if(function_exists('gzcompress'))
		$this->compress = $compress;
	else
		$this->compress = false;
}

function AddPage($orientation='', $size='', $rotation=0)
{
	if($this->state==0)
		$this->_begindoc();
	$family = $this->FontFamily;
	$style = $this->FontStyle.($this->underline ? 'U' : '');
	$fontsize = $this->FontSizePt;
	$lw = $this->LineWidth;
	$dc = $this->DrawColor;
	$fc = $this->FillColor;
	$tc = $this->TextColor;
	$cf = $this->ColorFlag;
	if($this->page>0)
	{
		$this->InFooter = true;
		$this->Footer();
		$this->InFooter = false;
		$this->_endpage();
	}
	$this->_beginpage($orientation,$size,$rotation);
	$this->_out(sprintf('2 J %.2F w',$this->LineWidth*$this->k));
	if($family)
		$this->SetFont($family,$style,$fontsize);
	$this->DrawColor = $dc;
	if($dc!='0 G')
		$this->_out($dc);
	$this->FillColor = $fc;
	if($fc!='0 g')
		$this->_out($fc);
	$this->TextColor = $tc;
	$this->ColorFlag = $cf;
}

function Header()
{
}

function Footer()
{
}

function PageNo()
{
	return $this->page;
}

function SetDrawColor($r, $g=null, $b=null)
{
	if(($r==0 && $g==0 && $b==0) || $g===null)
		$this->DrawColor = sprintf('%.3F G',$r/255);
	else
		$this->DrawColor = sprintf('%.3F %.3F %.3F RG',$r/255,$g/255,$b/255);
	if($this->page>0)
		$this->_out($this->DrawColor);
}

function SetFillColor($r, $g=null, $b=null)
{
	if(($r==0 && $g==0 && $b==0) || $g===null)
		$this->FillColor = sprintf('%.3F g',$r/255);
	else
		$this->FillColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
	$this->ColorFlag = ($this->FillColor!=$this->TextColor);
	if($this->page>0)
		$this->_out($this->FillColor);
}

function SetTextColor($r, $g=null, $b=null)
{
	if(($r==0 && $g==0 && $b==0) || $g===null)
		$this->TextColor = sprintf('%.3F g',$r/255);
	else
		$this->TextColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
	$this->ColorFlag = ($this->FillColor!=$this->TextColor);
}

function GetStringWidth($s)
{
	$s = (string)$s;
	$cw = &$this->CurrentFont['cw'];
	$w = 0;
	$l = strlen($s);
	for($i=0;$i<$l;$i++)
		$w += $cw[$s[$i]];
	return $w*$this->FontSize/1000;
}

function SetLineWidth($width)
{
	$this->LineWidth = $width;
	if($this->page>0)
		$this->_out(sprintf('%.2F w',$width*$this->k));
}

function Line($x1, $y1, $x2, $y2)
{
	$this->_out(sprintf('%.2F %.2F m %.2F %.2F l S',$x1*$this->k,($this->h-$y1)*$this->k,$x2*$this->k,($this->h-$y2)*$this->k));
}

function Rect($x, $y, $w, $h, $style='')
{
	$op = ($style=='F') ? 'f' : (($style=='FD' || $style=='DF') ? 'B' : 'S');
	$this->_out(sprintf('%.2F %.2F %.2F %.2F re %s',$x*$this->k,($this->h-$y)*$this->k,$w*$this->k,-$h*$this->k,$op));
}

function SetFont($family, $style='', $size=0)
{
	$family = strtolower($family);
	if($family=='')
		$family = $this->FontFamily;
	if($family=='arial')
		$family = 'helvetica';
	elseif($family=='symbol' || $family=='zapfdingbats')
		$style = '';
	$style = strtoupper($style);
	if(strpos($style,'U')!==false)
	{
		$this->underline = true;
		$style = str_replace('U','',$style);
	}
	else
		$this->underline = false;
	if($style=='IB')
		$style = 'BI';
	if($size==0)
		$size = $this->FontSizePt;
	if($this->FontFamily==$family && $this->FontStyle==$style && $this->FontSizePt==$size)
		return;
	$fontkey = $family.$style;
	if(!isset($this->fonts[$fontkey]))
		$this->AddFont($family,$style);
	$this->FontFamily = $family;
	$this->FontStyle = $style;
	$this->FontSizePt = $size;
	$this->FontSize = $size/$this->k;
	$this->CurrentFont = &$this->fonts[$fontkey];
	if($this->page>0)
		$this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
}

function SetFontSize($size)
{
	if($this->FontSizePt==$size)
		return;
	$this->FontSizePt = $size;
	$this->FontSize = $size/$this->k;
	if($this->page>0)
		$this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
}

function AddFont($family, $style='', $file='')
{
	$family = strtolower($family);
	if($family=='arial')
		$family = 'helvetica';
	$style = strtoupper($style);
	if($style=='IB')
		$style = 'BI';
	if(isset($this->fonts[$family.$style]))
		$this->Error('Font already added: '.$family.' '.$style);
	if($file=='')
		$file = str_replace(' ','',$family).strtolower($style).'.php';
	$fontkey = $family.$style;
	$this->fonts[$fontkey] = array('i'=>count($this->fonts)+1,'type'=>'Core','name'=>$this->CoreFonts[$family]);
}

function Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='')
{
	if(!isset($this->images[$file]))
	{
		//First use of this image, get info
		if($type=='')
		{
			$pos = strrpos($file,'.');
			if(!$pos)
				$this->Error('Image file has no extension and no type was specified: '.$file);
			$type = substr($file,$pos+1);
		}
		$type = strtolower($type);
		// Deprecated in PHP 8; ignore magic quotes toggles.
		if($type=='png')
			$info = $this->_parsepng($file);
		else
			$info = $this->_parsejpeg($file);
		$info['i'] = count($this->images)+1;
		$this->images[$file] = $info;
	}
	else
		$info = $this->images[$file];

	if($w==0 && $h==0)
	{
		//Put image at 72 dpi
		$w = $info['w']/$this->k;
		$h = $info['h']/$this->k;
	}
	elseif($w==0)
		$w = $h*$info['w']/$info['h'];
	elseif($h==0)
		$h = $w*$info['h']/$info['w'];
	if($x===null)
		$x = $this->x;
	if($y===null)
		$y = $this->y;
	$this->_out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q',$w*$this->k,$h*$this->k,$x*$this->k,($this->h-$y-$h)*$this->k,$info['i']));
	if($link)
		$this->Link($x,$y,$w,$h,$link);
}

function Ln($h=null)
{
	$this->x = $this->lMargin;
	if($h===null)
		$this->y += $this->lasth;
	else
		$this->y += $h;
}

function GetX()
{
	return $this->x;
}

function SetX($x)
{
	if($x>=0)
		$this->x = $x;
	else
		$this->x = $this->w+$x;
}

function GetY()
{
	return $this->y;
}

function SetY($y, $resetX=true)
{
	$this->y = ($y>=0) ? $y : $this->h+$y;
	if($resetX)
		$this->x = $this->lMargin;
}

function SetXY($x, $y)
{
	$this->SetY($y,false);
	$this->SetX($x);
}

function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='')
{
	$k = $this->k;
	if($this->y+$h>$this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak())
	{
		$x = $this->x;
		$ws = $this->ws;
		if($ws>0)
		{
			$this->ws = 0;
			$this->_out('0 Tw');
		}
		$this->AddPage($this->CurOrientation,$this->CurPageSize);
		$this->x = $x;
		if($ws>0)
		{
			$this->ws = $ws;
			$this->_out(sprintf('%.3F Tw',$ws*$k));
		}
	}
	if($w==0)
		$w = $this->w-$this->rMargin-$this->x;
	$s = '';
	if($fill || $border==1)
	{
		$op = ($fill) ? (($border==1) ? 'B' : 'f') : 'S';
		$s = sprintf('%.2F %.2F %.2F %.2F re %s ',$this->x*$k,($this->h-$this->y)*$k,$w*$k,-$h*$k,$op);
	}
	if(is_string($border))
	{
		$x = $this->x;
		$y = $this->y;
		if(strpos($border,'L')!==false)
			$s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,$x*$k,($this->h-($y+$h))*$k);
		if(strpos($border,'T')!==false)
			$s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x*$k, ($this->h-$y)*$k, ($x+$w)*$k, ($this->h-$y)*$k);
		if(strpos($border,'R')!==false)
			$s .= sprintf('%.2F %.2F m %.2F %.2F l S ', ($x+$w)*$k, ($this->h-$y)*$k, ($x+$w)*$k, ($this->h-($y+$h))*$k);
		if(strpos($border,'B')!==false)
			$s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x*$k, ($this->h-($y+$h))*$k, ($x+$w)*$k, ($this->h-($y+$h))*$k);
	}
	if($txt!=='')
	{
		if($this->ColorFlag)
			$s .= 'q '.$this->TextColor.' ';
		$txt = str_replace(')','\\)',str_replace('(','\\(',str_replace('\\','\\\\',$txt)));
		$s .= sprintf('BT %.2F %.2F Td (%s) Tj ET',$this->x*$k,($this->h-($this->y+.5*$h+0.3*$this->FontSize))*$k,$txt);
		if($this->underline)
			$s .= ' '.$this->_dounderline($this->x,$this->y,$txt);
		if($this->ColorFlag)
			$s .= ' Q';
		if($link)
			$this->Link($this->x,$this->y,$w,$h,$link);
	}
	if($s)
		$this->_out($s);
	$this->lasth = $h;
	if($ln>0)
	{
		$this->y += $h;
		if($ln==1)
			$this->x = $this->lMargin;
	}
	else
		$this->x += $w;
}

function MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false)
{
	$cw = &$this->CurrentFont['cw'];
	if($w==0)
		$w = $this->w-$this->rMargin-$this->x;
	$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
	$s = str_replace("\r",'',$txt);
	$nb = strlen($s);
	if($nb>0 && $s[$nb-1]=="\n")
		$nb--;
	$b = 0;
	if($border)
	{
		if($border==1)
		{
			$border = 'LTRB';
			$b = 'LRT';
			$b2 = 'LR';
		}
		else
		{
			$b2 = '';
			if(strpos($border,'L')!==false)
				$b2 .= 'L';
			if(strpos($border,'R')!==false)
				$b2 .= 'R';
			$b = (strpos($border,'T')!==false) ? $b2.'T' : $b2;
		}
	}
	$sep = -1;
	$i = 0;
	$j = 0;
	$l = 0;
	$ns = 0;
	$nl = 1;
	while($i<$nb)
	{
		$c = $s[$i];
		if($c=="\n")
		{
			if($this->ws>0)
			{
				$this->ws = 0;
				$this->_out('0 Tw');
			}
			$this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
			$i++;
			$sep = -1;
			$j = $i;
			$l = 0;
			$ns = 0;
			$nl++;
			if($border && $nl==2)
				$b = $b2;
			continue;
		}
		if($c==' ')
		{
			$sep = $i;
			$ls = $l;
			$ns++;
		}
		$l += $cw[$c];
		if($l>$wmax)
		{
			if($sep==-1)
			{
				if($i==$j)
					$i++;
				if($this->ws>0)
				{
					$this->ws = 0;
					$this->_out('0 Tw');
				}
				$this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
			}
			else
			{
				if($align=='J')
				{
					$this->ws = ($ns>1) ? ($wmax-$ls)/1000*$this->FontSize/($ns-1) : 0;
					$this->_out(sprintf('%.3F Tw',$this->ws*$this->k));
				}
				$this->Cell($w,$h,substr($s,$j,$sep-$j),$b,2,$align,$fill);
				$i = $sep+1;
			}
			$sep = -1;
			$j = $i;
			$l = 0;
			$ns = 0;
			$nl++;
			if($border && $nl==2)
				$b = $b2;
		}
		else
			$i++;
	}
	if($this->ws>0)
	{
		$this->ws = 0;
		$this->_out('0 Tw');
	}
	if($border && strpos($border,'B')!==false)
		$b .= 'B';
	$this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
	$this->x = $this->lMargin;
}

function Output($name='', $dest='')
{
	if($this->state<3)
		$this->Close();
	$dest = strtoupper($dest);
	if($dest=='')
	{
		if($name=='')
		{
			$name = 'doc.pdf';
			$dest = 'I';
		}
		else
			$dest = 'F';
	}
	switch($dest)
	{
		case 'I':
			header('Content-Type: application/pdf');
			header('Content-Disposition: inline; filename="'.$name.'"');
			echo $this->buffer;
			break;
		case 'D':
			header('Content-Type: application/x-download');
			header('Content-Disposition: attachment; filename="'.$name.'"');
			echo $this->buffer;
			break;
		case 'F':
			$f = @fopen($name,'wb');
			if(!$f)
				$this->Error('Unable to create output file: '.$name);
			fwrite($f,$this->buffer,strlen($this->buffer));
			fclose($f);
			break;
		case 'S':
			return $this->buffer;
		default:
			$this->Error('Incorrect output destination: '.$dest);
	}
	return '';
}

/* Private and internal methods */
function _dochecks()
{
	if(ini_get('mbstring.func_overload') & 2)
		$this->Error('mbstring overloading must be disabled');
	// get_magic_quotes_runtime removed in PHP 8; no-op for modern installs
}

function _getpagesize($size)
{
	if(is_string($size))
	{
		$size = strtolower($size);
		if(!isset($this->StdPageSizes[$size]))
			$this->Error('Unknown page size: '.$size);
		$a = $this->StdPageSizes[$size];
		return array($a[0]/$this->k, $a[1]/$this->k);
	}
	else
	{
		if($size[0]<=0 || $size[1]<=0)
			$this->Error('Invalid page size: '.$size[0].' x '.$size[1]);
		return array($size[0]/$this->k, $size[1]/$this->k);
	}
}

function _beginpage($orientation, $size, $rotation)
{
	$this->page++;
	$this->pages[$this->page] = '';
	$this->state = 2;
	$this->x = $this->lMargin;
	$this->y = $this->tMargin;
	$this->FontFamily = '';
	if($orientation=='')
		$orientation = $this->DefOrientation;
	else
		$orientation = strtoupper($orientation[0]);
	if($size=='')
		$size = $this->DefPageSize;
	else
		$size = $this->_getpagesize($size);
	if($orientation!=$this->CurOrientation || $size[0]!=$this->CurPageSize[0] || $size[1]!=$this->CurPageSize[1])
		$this->PageSizes[$this->page] = array($size[0]*$this->k, $size[1]*$this->k, $orientation);
	if($orientation=='P')
	{
		$this->w = $size[0];
		$this->h = $size[1];
	}
	else
	{
		$this->w = $size[1];
		$this->h = $size[0];
	}
	$this->wPt = $this->w*$this->k;
	$this->hPt = $this->h*$this->k;
	$this->PageBreakTrigger = $this->h-$this->bMargin;
	$this->CurOrientation = $orientation;
	$this->CurPageSize = $size;
	$this->CurRotation = $rotation;
}

function _endpage()
{
	$this->state = 1;
}

function _loadpng($file)
{
	//Read whole file into memory
	if(!function_exists('file_get_contents'))
	{
		$f = fopen($file,'rb');
		$data = '';
		while(!feof($f))
			$data .= fread($f,4096);
		fclose($f);
	}
	else
		$data = file_get_contents($file);
	if($data===false)
		$this->Error('Can\'t open image file: '.$file);

	return $data;
}

function _parsepng($file)
{
	$info = getimagesize($file);
	if(!$info)
		$this->Error('Can\'t open image file: '.$file);
	if($info[2]!=4)
		$this->Error('Unsupported PNG image: '.$file);
	$w = $info[0];
	$h = $info[1];
	$data = file_get_contents($file);
	$tmp = $this->_tempfilename();
	file_put_contents($tmp, $data);
	$im = imagecreatefrompng($tmp);
	unlink($tmp);
	if(!$im)
		$this->Error('Unsupported PNG image: '.$file);
	imageinterlace($im,0);
	$tmp = $this->_tempfilename();
	imagepng($im,$tmp);
	imagedestroy($im);
	$f = fopen($tmp,'rb');
	$data = '';
	while(!feof($f))
		$data .= fread($f,8192);
	fclose($f);
	unlink($tmp);
	return array('w'=>$w,'h'=>$h,'cs'=>'DeviceRGB','bpc'=>8,'f'=>'FlateDecode','data'=>$data);
}

function _parsejpeg($file)
{
	$a = getimagesize($file);
	if(!$a)
		$this->Error('Can\'t open image file: '.$file);
	if($a[2]!=2)
		$this->Error('Unsupported JPEG image: '.$file);
	$f = fopen($file,'rb');
	$data = '';
	while(!feof($f))
		$data .= fread($f,4096);
	fclose($f);
	return array('w'=>$a[0],'h'=>$a[1],'cs'=>'DeviceRGB','bpc'=>8,'f'=>'DCTDecode','data'=>$data);
}

function _textstring($s)
{
	return '('.str_replace(array('\\','(',')',"\r"),array('\\\\','\\(','\\)','\\r'),$s).')';
}

function _out($s)
{
	if($this->state==2)
		$this->pages[$this->page] .= $s."\n";
	else
		$this->buffer .= $s."\n";
}

function _tempfilename()
{
	return tempnam(sys_get_temp_dir(),'fpdf');
}

function Error($msg)
{
	throw new \RuntimeException('FPDF error: '.$msg);
}

function Close()
{
	if($this->state==3)
		return;
	if($this->page==0)
		$this->AddPage();
	$this->InFooter = true;
	$this->Footer();
	$this->InFooter = false;
	$this->_endpage();
	$this->_enddoc();
}

function _enddoc()
{
	$this->_putheader();
	$this->_putpages();
	$this->_putresources();
	$this->_putinfo();
	$this->_putcatalog();
	$this->_puttrailer();
	$this->_endobj();
	$this->state = 3;
	$this->buffer = "%PDF-".$this->PDFVersion."\n".$this->buffer;
}

function _putpages()
{
	$nb = $this->page;
	if(!empty($this->AliasNbPages))
	{
		for($n=1;$n<=$nb;$n++)
			$this->pages[$n] = str_replace($this->AliasNbPages,$nb,$this->pages[$n]);
	}
	if($this->DefOrientation=='P')
	{
		$wPt = $this->DefPageSize[0]*$this->k;
		$hPt = $this->DefPageSize[1]*$this->k;
	}
	else
	{
		$wPt = $this->DefPageSize[1]*$this->k;
		$hPt = $this->DefPageSize[0]*$this->k;
	}
	$filter = ($this->compress) ? '/Filter /FlateDecode ' : '';
	for($n=1;$n<=$nb;$n++)
	{
		$this->_newobj();
		$this->_out('<</Type /Page');
		$this->_out('/Parent 1 0 R');
		if(isset($this->PageSizes[$n]))
			$this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->PageSizes[$n][0], $this->PageSizes[$n][1]));
		$this->_out('/Resources 2 0 R');
		if($this->WithAlpha)
			$this->_out('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
		$this->_out('/Contents '.($this->n+1).' 0 R>>');
		$this->_out('endobj');
		$p = ($this->compress) ? gzcompress($this->pages[$n]) : $this->pages[$n];
		$this->_newobj();
		$this->_out('<<'.$filter.'/Length '.strlen($p).'>>');
		$this->_putstream($p);
		$this->_out('endobj');
	}
	$this->offsets[1] = strlen($this->buffer);
	$this->_out('1 0 obj');
	$this->_out('<</Type /Pages');
	$kids = '/Kids [';
	for($n=0;$n<$nb;$n++)
		$kids .= (3+2*$n).' 0 R ';
	$kids .= ']';
	$this->_out($kids);
	$this->_out('/Count '.$nb);
	$this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]',$wPt,$hPt));
	$this->_out('>>');
	$this->_out('endobj');
}

function _putresources()
{
	$this->_putimages();

	$this->_newobj();
	$this->_out('2 0 obj');
	$this->_out('<<');
	$this->_out('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
	$this->_out('/Font << /F1 3 0 R >>');
	$this->_out('/XObject <<');
	foreach($this->images as $file=>$info)
		$this->_out('/I'.$info['i'].' '.$info['n'].' 0 R');
	$this->_out('>>');
	$this->_out('>>');
	$this->_out('endobj');

	//Font object
	$this->_newobj();
	$this->_out('3 0 obj');
	$this->_out('<</Type /Font');
	$this->_out('/Subtype /Type1');
	$this->_out('/BaseFont /Helvetica');
	$this->_out('>>');
	$this->_out('endobj');

}

// Images are written before the resource dictionary so n is known
function _putimages()
{
	$filter = ($this->compress) ? '/Filter /FlateDecode ' : '';
	foreach($this->images as $file=>$info)
	{
		$this->_newobj();
		$this->images[$file]['n'] = $this->n;
		$this->_out('<</Type /XObject');
		$this->_out('/Subtype /Image');
		$this->_out('/Width '.$info['w']);
		$this->_out('/Height '.$info['h']);
		$this->_out('/ColorSpace /DeviceRGB');
		$this->_out('/BitsPerComponent 8');
		$this->_out('/Length '.strlen($info['data']).'>>');
		$this->_putstream($info['data']);
		$this->_out('endobj');
	}
}

function _putinfo()
{
	$this->_out('/Producer '.$this->_textstring('FPDF '.FPDF_VERSION));
	if(!empty($this->title))
		$this->_out('/Title '.$this->_textstring($this->title));
	if(!empty($this->subject))
		$this->_out('/Subject '.$this->_textstring($this->subject));
	if(!empty($this->author))
		$this->_out('/Author '.$this->_textstring($this->author));
	if(!empty($this->keywords))
		$this->_out('/Keywords '.$this->_textstring($this->keywords));
	if(!empty($this->creator))
		$this->_out('/Creator '.$this->_textstring($this->creator));
}

function _putcatalog()
{
	$this->_out('/Type /Catalog');
	$this->_out('/Pages 1 0 R');
	if($this->ZoomMode=='fullpage')
		$this->_out('/OpenAction [3 0 R /Fit]');
	elseif($this->ZoomMode=='fullwidth')
		$this->_out('/OpenAction [3 0 R /FitH null]');
	elseif($this->ZoomMode=='real')
		$this->_out('/OpenAction [3 0 R /XYZ null null 1]');
	if($this->LayoutMode=='single')
		$this->_out('/PageLayout /SinglePage');
	elseif($this->LayoutMode=='continuous')
		$this->_out('/PageLayout /OneColumn');
	elseif($this->LayoutMode=='two')
		$this->_out('/PageLayout /TwoColumnLeft');
}

function _puttrailer()
{
	$this->_out('/Size '.($this->n+1));
	$this->_out('/Root 4 0 R');
	$this->_out('/Info 5 0 R');
}

function _endobj()
{
	$this->buffer .= "endobj\n";
}

// Minimal _begindoc to init buffers/state
function _begindoc()
{
	$this->state = 1;
	$this->pages = array();
	$this->PageSizes = array();
	$this->buffer = '';
	$this->offsets = array();
	$this->images = array();
	$this->n = 0;
}

function _newobj()
{
	$this->n++;
	$this->offsets[$this->n] = strlen($this->buffer);
	$this->buffer .= $this->n." 0 obj\n";
}

function _putheader()
{
}

function _putstream($s)
{
	$this->_out('stream');
	$this->_out($s);
	$this->_out('endstream');
}
}
