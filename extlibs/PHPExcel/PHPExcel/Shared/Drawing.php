<?php


class PHPExcel_Shared_Drawing
{
	public static function pixelsToEMU($pValue = 0) {
		return round($pValue * 9525);
	}

	public static function EMUToPixels($pValue = 0) {
		if ($pValue != 0) {
			return round($pValue / 9525);
		} else {
			return 0;
		}
	}

	public static function pixelsToCellDimension($pValue = 0, PHPExcel_Style_Font $pDefaultFont) {
		$name = $pDefaultFont->getName();
		$size = $pDefaultFont->getSize();

		if (isset(PHPExcel_Shared_Font::$defaultColumnWidths[$name][$size])) {
			$colWidth = $pValue
				* PHPExcel_Shared_Font::$defaultColumnWidths[$name][$size]['width']
				/ PHPExcel_Shared_Font::$defaultColumnWidths[$name][$size]['px'];
		} else {
			$colWidth = $pValue * 11
				* PHPExcel_Shared_Font::$defaultColumnWidths['Calibri'][11]['width']
				/ PHPExcel_Shared_Font::$defaultColumnWidths['Calibri'][11]['px'] / $size;
		}

		return $colWidth;
	}

	public static function cellDimensionToPixels($pValue = 0, PHPExcel_Style_Font $pDefaultFont) {
		$name = $pDefaultFont->getName();
		$size = $pDefaultFont->getSize();

		if (isset(PHPExcel_Shared_Font::$defaultColumnWidths[$name][$size])) {
			$colWidth = $pValue
				* PHPExcel_Shared_Font::$defaultColumnWidths[$name][$size]['px']
				/ PHPExcel_Shared_Font::$defaultColumnWidths[$name][$size]['width'];

		} else {
			$colWidth = $pValue * $size
				* PHPExcel_Shared_Font::$defaultColumnWidths['Calibri'][11]['px']
				/ PHPExcel_Shared_Font::$defaultColumnWidths['Calibri'][11]['width'] / 11;
		}

		$colWidth = (int) round($colWidth);

		return $colWidth;
	}

	public static function pixelsToPoints($pValue = 0) {
		return $pValue * 0.67777777;
	}

	public static function pointsToPixels($pValue = 0) {
		if ($pValue != 0) {
			return (int) ceil($pValue * 1.333333333);
		} else {
			return 0;
		}
	}

	public static function degreesToAngle($pValue = 0) {
		return (int)round($pValue * 60000);
	}

	public static function angleToDegrees($pValue = 0) {
		if ($pValue != 0) {
			return round($pValue / 60000);
		} else {
			return 0;
		}
	}

	public static function imagecreatefrombmp($p_sFile)
	{
        $file    =    fopen($p_sFile,"rb");
        $read    =    fread($file,10);
        while(!feof($file)&&($read<>""))
            $read    .=    fread($file,1024);

        $temp    =    unpack("H*",$read);
        $hex    =    $temp[1];
        $header    =    substr($hex,0,108);

        if (substr($header,0,4)=="424d")
        {
            $header_parts    =    str_split($header,2);

            $width            =    hexdec($header_parts[19].$header_parts[18]);

            $height            =    hexdec($header_parts[23].$header_parts[22]);

            unset($header_parts);
        }

        $x                =    0;
        $y                =    1;

        $image            =    imagecreatetruecolor($width,$height);

        $body            =    substr($hex,108);

        $body_size        =    (strlen($body)/2);
        $header_size    =    ($width*$height);

        $usePadding        =    ($body_size>($header_size*3)+4);

        for ($i=0;$i<$body_size;$i+=3)
        {
            if ($x>=$width)
            {
                if ($usePadding)
                    $i    +=    $width%4;

                $x    =    0;

                $y++;

                if ($y>$height)
                    break;
            }

            $i_pos    =    $i*2;
            $r        =    hexdec($body[$i_pos+4].$body[$i_pos+5]);
            $g        =    hexdec($body[$i_pos+2].$body[$i_pos+3]);
            $b        =    hexdec($body[$i_pos].$body[$i_pos+1]);

            $color    =    imagecolorallocate($image,$r,$g,$b);
            imagesetpixel($image,$x,$height-$y,$color);

            $x++;
        }

        unset($body);

        return $image;
	}

}
