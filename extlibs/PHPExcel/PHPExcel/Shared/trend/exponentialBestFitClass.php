<?php


require_once(PHPEXCEL_ROOT . 'PHPExcel/Shared/trend/bestFitClass.php');


class PHPExcel_Exponential_Best_Fit extends PHPExcel_Best_Fit
{
	protected $_bestFitType		= 'exponential';


	public function getValueOfYForX($xValue) {
		return $this->getIntersect() * pow($this->getSlope(),($xValue - $this->_Xoffset));
	}	


	public function getValueOfXForY($yValue) {
		return log(($yValue + $this->_Yoffset) / $this->getIntersect()) / log($this->getSlope());
	}	


	public function getEquation($dp=0) {
		$slope = $this->getSlope($dp);
		$intersect = $this->getIntersect($dp);

		return 'Y = '.$intersect.' * '.$slope.'^X';
	}	


	public function getSlope($dp=0) {
		if ($dp != 0) {
			return round(exp($this->_slope),$dp);
		}
		return exp($this->_slope);
	}	


	public function getIntersect($dp=0) {
		if ($dp != 0) {
			return round(exp($this->_intersect),$dp);
		}
		return exp($this->_intersect);
	}	


	private function _exponential_regression($yValues, $xValues, $const) {
		foreach($yValues as &$value) {
			if ($value < 0.0) {
				$value = 0 - log(abs($value));
			} elseif ($value > 0.0) {
				$value = log($value);
			}
		}
		unset($value);

		$this->_leastSquareFit($yValues, $xValues, $const);
	}	


	function __construct($yValues, $xValues=array(), $const=True) {
		if (parent::__construct($yValues, $xValues) !== False) {
			$this->_exponential_regression($yValues, $xValues, $const);
		}
	}	

}	