<?php


class PHPExcel_Chart_Exception extends PHPExcel_Exception {
	public static function errorHandlerCallback($code, $string, $file, $line, $context) {
		$e = new self($string, $code);
		$e->line = $line;
		$e->file = $file;
		throw $e;
	}
}
