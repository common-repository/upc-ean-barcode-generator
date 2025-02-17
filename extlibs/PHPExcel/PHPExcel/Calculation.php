<?php


if (!defined('PHPEXCEL_ROOT')) {
	define('PHPEXCEL_ROOT', dirname(__FILE__) . '/../');
	require(PHPEXCEL_ROOT . 'PHPExcel/Autoloader.php');
}


if (!defined('CALCULATION_REGEXP_CELLREF')) {
	if(defined('PREG_BAD_UTF8_ERROR')) {
		define('CALCULATION_REGEXP_CELLREF','((([^\s,!&%^\/\*\+<>=-]*)|(\'[^\']*\')|(\"[^\"]*\"))!)?\$?([a-z]{1,3})\$?(\d{1,7})');
		define('CALCULATION_REGEXP_NAMEDRANGE','((([^\s,!&%^\/\*\+<>=-]*)|(\'[^\']*\')|(\"[^\"]*\"))!)?([_A-Z][_A-Z0-9\.]*)');
	} else {
		define('CALCULATION_REGEXP_CELLREF','(((\w*)|(\'[^\']*\')|(\"[^\"]*\"))!)?\$?([a-z]{1,3})\$?(\d+)');
		define('CALCULATION_REGEXP_NAMEDRANGE','(((\w*)|(\'.*\')|(\".*\"))!)?([_A-Z][_A-Z0-9\.]*)');
	}
}


class PHPExcel_Calculation {

	const CALCULATION_REGEXP_NUMBER		= '[-+]?\d*\.?\d+(e[-+]?\d+)?';
	const CALCULATION_REGEXP_STRING		= '"(?:[^"]|"")*"';
	const CALCULATION_REGEXP_OPENBRACE	= '\(';
	const CALCULATION_REGEXP_FUNCTION	= '@?([A-Z][A-Z0-9\.]*)[\s]*\(';
	const CALCULATION_REGEXP_CELLREF	= CALCULATION_REGEXP_CELLREF;
	const CALCULATION_REGEXP_NAMEDRANGE	= CALCULATION_REGEXP_NAMEDRANGE;
	const CALCULATION_REGEXP_ERROR		= '\#[A-Z][A-Z0_\/]*[!\?]?';


	const RETURN_ARRAY_AS_ERROR = 'error';
	const RETURN_ARRAY_AS_VALUE = 'value';
	const RETURN_ARRAY_AS_ARRAY = 'array';

	private static $returnArrayAsType	= self::RETURN_ARRAY_AS_VALUE;


	private static $_instance;


    private $_workbook;

    private static $_workbookSets;

	private $_calculationCache = array ();


	private $_calculationCacheEnabled = TRUE;


	private static $_operators			= array('+' => TRUE,	'-' => TRUE,	'*' => TRUE,	'/' => TRUE,
												'^' => TRUE,	'&' => TRUE,	'%' => FALSE,	'~' => FALSE,
												'>' => TRUE,	'<' => TRUE,	'=' => TRUE,	'>=' => TRUE,
												'<=' => TRUE,	'<>' => TRUE,	'|' => TRUE,	':' => TRUE
											   );


	private static $_binaryOperators	= array('+' => TRUE,	'-' => TRUE,	'*' => TRUE,	'/' => TRUE,
												'^' => TRUE,	'&' => TRUE,	'>' => TRUE,	'<' => TRUE,
												'=' => TRUE,	'>=' => TRUE,	'<=' => TRUE,	'<>' => TRUE,
												'|' => TRUE,	':' => TRUE
											   );

	private $debugLog;

	public $suppressFormulaErrors = FALSE;

	public $formulaError = NULL;

	private $_cyclicReferenceStack;

	private $_cellStack = array();

	private $_cyclicFormulaCount = 0;

	private $_cyclicFormulaCell = '';

	public $cyclicFormulaCount = 0;

	private $_savedPrecision	= 14;


	private static $_localeLanguage = 'en_us';					

	private static $_validLocaleLanguages = array(	'en'		
												 );
	private static $_localeArgumentSeparator = ',';
	private static $_localeFunctions = array();

	public static $_localeBoolean = array(	'TRUE'	=> 'TRUE',
											'FALSE'	=> 'FALSE',
											'NULL'	=> 'NULL'
										  );


	private static $_ExcelConstants = array('TRUE'	=> TRUE,
											'FALSE'	=> FALSE,
											'NULL'	=> NULL
										   );

	private static $_PHPExcelFunctions = array(	
				'ABS'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'abs',
												 'argumentCount'	=>	'1'
												),
				'ACCRINT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::ACCRINT',
												 'argumentCount'	=>	'4-7'
												),
				'ACCRINTM'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::ACCRINTM',
												 'argumentCount'	=>	'3-5'
												),
				'ACOS'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'acos',
												 'argumentCount'	=>	'1'
												),
				'ACOSH'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'acosh',
												 'argumentCount'	=>	'1'
												),
				'ADDRESS'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_LookupRef::CELL_ADDRESS',
												 'argumentCount'	=>	'2-5'
												),
				'AMORDEGRC'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::AMORDEGRC',
												 'argumentCount'	=>	'6,7'
												),
				'AMORLINC'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::AMORLINC',
												 'argumentCount'	=>	'6,7'
												),
				'AND'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOGICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Logical::LOGICAL_AND',
												 'argumentCount'	=>	'1+'
												),
				'AREAS'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'1'
												),
				'ASC'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'1'
												),
				'ASIN'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'asin',
												 'argumentCount'	=>	'1'
												),
				'ASINH'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'asinh',
												 'argumentCount'	=>	'1'
												),
				'ATAN'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'atan',
												 'argumentCount'	=>	'1'
												),
				'ATAN2'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::ATAN2',
												 'argumentCount'	=>	'2'
												),
				'ATANH'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'atanh',
												 'argumentCount'	=>	'1'
												),
				'AVEDEV'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::AVEDEV',
												 'argumentCount'	=>	'1+'
												),
				'AVERAGE'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::AVERAGE',
												 'argumentCount'	=>	'1+'
												),
				'AVERAGEA'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::AVERAGEA',
												 'argumentCount'	=>	'1+'
												),
				'AVERAGEIF'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::AVERAGEIF',
												 'argumentCount'	=>	'2,3'
												),
				'AVERAGEIFS'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'3+'
												),
				'BAHTTEXT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'1'
												),
				'BESSELI'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::BESSELI',
												 'argumentCount'	=>	'2'
												),
				'BESSELJ'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::BESSELJ',
												 'argumentCount'	=>	'2'
												),
				'BESSELK'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::BESSELK',
												 'argumentCount'	=>	'2'
												),
				'BESSELY'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::BESSELY',
												 'argumentCount'	=>	'2'
												),
				'BETADIST'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::BETADIST',
												 'argumentCount'	=>	'3-5'
												),
				'BETAINV'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::BETAINV',
												 'argumentCount'	=>	'3-5'
												),
				'BIN2DEC'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::BINTODEC',
												 'argumentCount'	=>	'1'
												),
				'BIN2HEX'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::BINTOHEX',
												 'argumentCount'	=>	'1,2'
												),
				'BIN2OCT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::BINTOOCT',
												 'argumentCount'	=>	'1,2'
												),
				'BINOMDIST'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::BINOMDIST',
												 'argumentCount'	=>	'4'
												),
				'CEILING'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::CEILING',
												 'argumentCount'	=>	'2'
												),
				'CELL'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'1,2'
												),
				'CHAR'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::CHARACTER',
												 'argumentCount'	=>	'1'
												),
				'CHIDIST'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::CHIDIST',
												 'argumentCount'	=>	'2'
												),
				'CHIINV'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::CHIINV',
												 'argumentCount'	=>	'2'
												),
				'CHITEST'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'2'
												),
				'CHOOSE'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_LookupRef::CHOOSE',
												 'argumentCount'	=>	'2+'
												),
				'CLEAN'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::TRIMNONPRINTABLE',
												 'argumentCount'	=>	'1'
												),
				'CODE'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::ASCIICODE',
												 'argumentCount'	=>	'1'
												),
				'COLUMN'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_LookupRef::COLUMN',
												 'argumentCount'	=>	'-1',
												 'passByReference'	=>	array(TRUE)
												),
				'COLUMNS'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_LookupRef::COLUMNS',
												 'argumentCount'	=>	'1'
												),
				'COMBIN'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::COMBIN',
												 'argumentCount'	=>	'2'
												),
				'COMPLEX'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::COMPLEX',
												 'argumentCount'	=>	'2,3'
												),
				'CONCATENATE'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::CONCATENATE',
												 'argumentCount'	=>	'1+'
												),
				'CONFIDENCE'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::CONFIDENCE',
												 'argumentCount'	=>	'3'
												),
				'CONVERT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::CONVERTUOM',
												 'argumentCount'	=>	'3'
												),
				'CORREL'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::CORREL',
												 'argumentCount'	=>	'2'
												),
				'COS'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'cos',
												 'argumentCount'	=>	'1'
												),
				'COSH'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'cosh',
												 'argumentCount'	=>	'1'
												),
				'COUNT'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::COUNT',
												 'argumentCount'	=>	'1+'
												),
				'COUNTA'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::COUNTA',
												 'argumentCount'	=>	'1+'
												),
				'COUNTBLANK'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::COUNTBLANK',
												 'argumentCount'	=>	'1'
												),
				'COUNTIF'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::COUNTIF',
												 'argumentCount'	=>	'2'
												),
				'COUNTIFS'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'2'
												),
				'COUPDAYBS'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::COUPDAYBS',
												 'argumentCount'	=>	'3,4'
												),
				'COUPDAYS'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::COUPDAYS',
												 'argumentCount'	=>	'3,4'
												),
				'COUPDAYSNC'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::COUPDAYSNC',
												 'argumentCount'	=>	'3,4'
												),
				'COUPNCD'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::COUPNCD',
												 'argumentCount'	=>	'3,4'
												),
				'COUPNUM'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::COUPNUM',
												 'argumentCount'	=>	'3,4'
												),
				'COUPPCD'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::COUPPCD',
												 'argumentCount'	=>	'3,4'
												),
				'COVAR'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::COVAR',
												 'argumentCount'	=>	'2'
												),
				'CRITBINOM'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::CRITBINOM',
												 'argumentCount'	=>	'3'
												),
				'CUBEKPIMEMBER'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_CUBE,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'?'
												),
				'CUBEMEMBER'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_CUBE,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'?'
												),
				'CUBEMEMBERPROPERTY'	=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_CUBE,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'?'
												),
				'CUBERANKEDMEMBER'		=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_CUBE,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'?'
												),
				'CUBESET'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_CUBE,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'?'
												),
				'CUBESETCOUNT'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_CUBE,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'?'
												),
				'CUBEVALUE'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_CUBE,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'?'
												),
				'CUMIPMT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::CUMIPMT',
												 'argumentCount'	=>	'6'
												),
				'CUMPRINC'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::CUMPRINC',
												 'argumentCount'	=>	'6'
												),
				'DATE'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::DATE',
												 'argumentCount'	=>	'3'
												),
				'DATEDIF'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::DATEDIF',
												 'argumentCount'	=>	'2,3'
												),
				'DATEVALUE'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::DATEVALUE',
												 'argumentCount'	=>	'1'
												),
				'DAVERAGE'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATABASE,
												 'functionCall'		=>	'PHPExcel_Calculation_Database::DAVERAGE',
												 'argumentCount'	=>	'3'
												),
				'DAY'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::DAYOFMONTH',
												 'argumentCount'	=>	'1'
												),
				'DAYS360'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::DAYS360',
												 'argumentCount'	=>	'2,3'
												),
				'DB'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::DB',
												 'argumentCount'	=>	'4,5'
												),
				'DCOUNT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATABASE,
												 'functionCall'		=>	'PHPExcel_Calculation_Database::DCOUNT',
												 'argumentCount'	=>	'3'
												),
				'DCOUNTA'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATABASE,
												 'functionCall'		=>	'PHPExcel_Calculation_Database::DCOUNTA',
												 'argumentCount'	=>	'3'
												),
				'DDB'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::DDB',
												 'argumentCount'	=>	'4,5'
												),
				'DEC2BIN'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::DECTOBIN',
												 'argumentCount'	=>	'1,2'
												),
				'DEC2HEX'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::DECTOHEX',
												 'argumentCount'	=>	'1,2'
												),
				'DEC2OCT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::DECTOOCT',
												 'argumentCount'	=>	'1,2'
												),
				'DEGREES'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'rad2deg',
												 'argumentCount'	=>	'1'
												),
				'DELTA'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::DELTA',
												 'argumentCount'	=>	'1,2'
												),
				'DEVSQ'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::DEVSQ',
												 'argumentCount'	=>	'1+'
												),
				'DGET'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATABASE,
												 'functionCall'		=>	'PHPExcel_Calculation_Database::DGET',
												 'argumentCount'	=>	'3'
												),
				'DISC'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::DISC',
												 'argumentCount'	=>	'4,5'
												),
				'DMAX'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATABASE,
												 'functionCall'		=>	'PHPExcel_Calculation_Database::DMAX',
												 'argumentCount'	=>	'3'
												),
				'DMIN'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATABASE,
												 'functionCall'		=>	'PHPExcel_Calculation_Database::DMIN',
												 'argumentCount'	=>	'3'
												),
				'DOLLAR'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::DOLLAR',
												 'argumentCount'	=>	'1,2'
												),
				'DOLLARDE'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::DOLLARDE',
												 'argumentCount'	=>	'2'
												),
				'DOLLARFR'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::DOLLARFR',
												 'argumentCount'	=>	'2'
												),
				'DPRODUCT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATABASE,
												 'functionCall'		=>	'PHPExcel_Calculation_Database::DPRODUCT',
												 'argumentCount'	=>	'3'
												),
				'DSTDEV'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATABASE,
												 'functionCall'		=>	'PHPExcel_Calculation_Database::DSTDEV',
												 'argumentCount'	=>	'3'
												),
				'DSTDEVP'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATABASE,
												 'functionCall'		=>	'PHPExcel_Calculation_Database::DSTDEVP',
												 'argumentCount'	=>	'3'
												),
				'DSUM'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATABASE,
												 'functionCall'		=>	'PHPExcel_Calculation_Database::DSUM',
												 'argumentCount'	=>	'3'
												),
				'DURATION'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'5,6'
												),
				'DVAR'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATABASE,
												 'functionCall'		=>	'PHPExcel_Calculation_Database::DVAR',
												 'argumentCount'	=>	'3'
												),
				'DVARP'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATABASE,
												 'functionCall'		=>	'PHPExcel_Calculation_Database::DVARP',
												 'argumentCount'	=>	'3'
												),
				'EDATE'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::EDATE',
												 'argumentCount'	=>	'2'
												),
				'EFFECT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::EFFECT',
												 'argumentCount'	=>	'2'
												),
				'EOMONTH'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::EOMONTH',
												 'argumentCount'	=>	'2'
												),
				'ERF'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::ERF',
												 'argumentCount'	=>	'1,2'
												),
				'ERFC'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::ERFC',
												 'argumentCount'	=>	'1'
												),
				'ERROR.TYPE'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::ERROR_TYPE',
												 'argumentCount'	=>	'1'
												),
				'EVEN'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::EVEN',
												 'argumentCount'	=>	'1'
												),
				'EXACT'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'2'
												),
				'EXP'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'exp',
												 'argumentCount'	=>	'1'
												),
				'EXPONDIST'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::EXPONDIST',
												 'argumentCount'	=>	'3'
												),
				'FACT'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::FACT',
												 'argumentCount'	=>	'1'
												),
				'FACTDOUBLE'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::FACTDOUBLE',
												 'argumentCount'	=>	'1'
												),
				'FALSE'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOGICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Logical::FALSE',
												 'argumentCount'	=>	'0'
												),
				'FDIST'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'3'
												),
				'FIND'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::SEARCHSENSITIVE',
												 'argumentCount'	=>	'2,3'
												),
				'FINDB'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::SEARCHSENSITIVE',
												 'argumentCount'	=>	'2,3'
												),
				'FINV'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'3'
												),
				'FISHER'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::FISHER',
												 'argumentCount'	=>	'1'
												),
				'FISHERINV'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::FISHERINV',
												 'argumentCount'	=>	'1'
												),
				'FIXED'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::FIXEDFORMAT',
												 'argumentCount'	=>	'1-3'
												),
				'FLOOR'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::FLOOR',
												 'argumentCount'	=>	'2'
												),
				'FORECAST'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::FORECAST',
												 'argumentCount'	=>	'3'
												),
				'FREQUENCY'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'2'
												),
				'FTEST'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'2'
												),
				'FV'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::FV',
												 'argumentCount'	=>	'3-5'
												),
				'FVSCHEDULE'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::FVSCHEDULE',
												 'argumentCount'	=>	'2'
												),
				'GAMMADIST'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::GAMMADIST',
												 'argumentCount'	=>	'4'
												),
				'GAMMAINV'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::GAMMAINV',
												 'argumentCount'	=>	'3'
												),
				'GAMMALN'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::GAMMALN',
												 'argumentCount'	=>	'1'
												),
				'GCD'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::GCD',
												 'argumentCount'	=>	'1+'
												),
				'GEOMEAN'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::GEOMEAN',
												 'argumentCount'	=>	'1+'
												),
				'GESTEP'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::GESTEP',
												 'argumentCount'	=>	'1,2'
												),
				'GETPIVOTDATA'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'2+'
												),
				'GROWTH'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::GROWTH',
												 'argumentCount'	=>	'1-4'
												),
				'HARMEAN'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::HARMEAN',
												 'argumentCount'	=>	'1+'
												),
				'HEX2BIN'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::HEXTOBIN',
												 'argumentCount'	=>	'1,2'
												),
				'HEX2DEC'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::HEXTODEC',
												 'argumentCount'	=>	'1'
												),
				'HEX2OCT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::HEXTOOCT',
												 'argumentCount'	=>	'1,2'
												),
				'HLOOKUP'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_LookupRef::HLOOKUP',
												 'argumentCount'	=>	'3,4'
												),
				'HOUR'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::HOUROFDAY',
												 'argumentCount'	=>	'1'
												),
				'HYPERLINK'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_LookupRef::HYPERLINK',
												 'argumentCount'	=>	'1,2',
												 'passCellReference'=>	TRUE
												),
				'HYPGEOMDIST'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::HYPGEOMDIST',
												 'argumentCount'	=>	'4'
												),
				'IF'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOGICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Logical::STATEMENT_IF',
												 'argumentCount'	=>	'1-3'
												),
				'IFERROR'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOGICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Logical::IFERROR',
												 'argumentCount'	=>	'2'
												),
				'IMABS'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMABS',
												 'argumentCount'	=>	'1'
												),
				'IMAGINARY'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMAGINARY',
												 'argumentCount'	=>	'1'
												),
				'IMARGUMENT'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMARGUMENT',
												 'argumentCount'	=>	'1'
												),
				'IMCONJUGATE'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMCONJUGATE',
												 'argumentCount'	=>	'1'
												),
				'IMCOS'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMCOS',
												 'argumentCount'	=>	'1'
												),
				'IMDIV'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMDIV',
												 'argumentCount'	=>	'2'
												),
				'IMEXP'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMEXP',
												 'argumentCount'	=>	'1'
												),
				'IMLN'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMLN',
												 'argumentCount'	=>	'1'
												),
				'IMLOG10'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMLOG10',
												 'argumentCount'	=>	'1'
												),
				'IMLOG2'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMLOG2',
												 'argumentCount'	=>	'1'
												),
				'IMPOWER'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMPOWER',
												 'argumentCount'	=>	'2'
												),
				'IMPRODUCT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMPRODUCT',
												 'argumentCount'	=>	'1+'
												),
				'IMREAL'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMREAL',
												 'argumentCount'	=>	'1'
												),
				'IMSIN'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMSIN',
												 'argumentCount'	=>	'1'
												),
				'IMSQRT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMSQRT',
												 'argumentCount'	=>	'1'
												),
				'IMSUB'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMSUB',
												 'argumentCount'	=>	'2'
												),
				'IMSUM'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::IMSUM',
												 'argumentCount'	=>	'1+'
												),
				'INDEX'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_LookupRef::INDEX',
												 'argumentCount'	=>	'1-4'
												),
				'INDIRECT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_LookupRef::INDIRECT',
												 'argumentCount'	=>	'1,2',
												 'passCellReference'=>	TRUE
												),
				'INFO'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'1'
												),
				'INT'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::INT',
												 'argumentCount'	=>	'1'
												),
				'INTERCEPT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::INTERCEPT',
												 'argumentCount'	=>	'2'
												),
				'INTRATE'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::INTRATE',
												 'argumentCount'	=>	'4,5'
												),
				'IPMT'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::IPMT',
												 'argumentCount'	=>	'4-6'
												),
				'IRR'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::IRR',
												 'argumentCount'	=>	'1,2'
												),
				'ISBLANK'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::IS_BLANK',
												 'argumentCount'	=>	'1'
												),
				'ISERR'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::IS_ERR',
												 'argumentCount'	=>	'1'
												),
				'ISERROR'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::IS_ERROR',
												 'argumentCount'	=>	'1'
												),
				'ISEVEN'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::IS_EVEN',
												 'argumentCount'	=>	'1'
												),
				'ISLOGICAL'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::IS_LOGICAL',
												 'argumentCount'	=>	'1'
												),
				'ISNA'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::IS_NA',
												 'argumentCount'	=>	'1'
												),
				'ISNONTEXT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::IS_NONTEXT',
												 'argumentCount'	=>	'1'
												),
				'ISNUMBER'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::IS_NUMBER',
												 'argumentCount'	=>	'1'
												),
				'ISODD'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::IS_ODD',
												 'argumentCount'	=>	'1'
												),
				'ISPMT'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::ISPMT',
												 'argumentCount'	=>	'4'
												),
				'ISREF'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'1'
												),
				'ISTEXT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::IS_TEXT',
												 'argumentCount'	=>	'1'
												),
				'JIS'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'1'
												),
				'KURT'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::KURT',
												 'argumentCount'	=>	'1+'
												),
				'LARGE'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::LARGE',
												 'argumentCount'	=>	'2'
												),
				'LCM'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::LCM',
												 'argumentCount'	=>	'1+'
												),
				'LEFT'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::LEFT',
												 'argumentCount'	=>	'1,2'
												),
				'LEFTB'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::LEFT',
												 'argumentCount'	=>	'1,2'
												),
				'LEN'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::STRINGLENGTH',
												 'argumentCount'	=>	'1'
												),
				'LENB'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::STRINGLENGTH',
												 'argumentCount'	=>	'1'
												),
				'LINEST'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::LINEST',
												 'argumentCount'	=>	'1-4'
												),
				'LN'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'log',
												 'argumentCount'	=>	'1'
												),
				'LOG'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::LOG_BASE',
												 'argumentCount'	=>	'1,2'
												),
				'LOG10'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'log10',
												 'argumentCount'	=>	'1'
												),
				'LOGEST'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::LOGEST',
												 'argumentCount'	=>	'1-4'
												),
				'LOGINV'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::LOGINV',
												 'argumentCount'	=>	'3'
												),
				'LOGNORMDIST'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::LOGNORMDIST',
												 'argumentCount'	=>	'3'
												),
				'LOOKUP'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_LookupRef::LOOKUP',
												 'argumentCount'	=>	'2,3'
												),
				'LOWER'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::LOWERCASE',
												 'argumentCount'	=>	'1'
												),
				'MATCH'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_LookupRef::MATCH',
												 'argumentCount'	=>	'2,3'
												),
				'MAX'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::MAX',
												 'argumentCount'	=>	'1+'
												),
				'MAXA'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::MAXA',
												 'argumentCount'	=>	'1+'
												),
				'MAXIF'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::MAXIF',
												 'argumentCount'	=>	'2+'
												),
				'MDETERM'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::MDETERM',
												 'argumentCount'	=>	'1'
												),
				'MDURATION'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'5,6'
												),
				'MEDIAN'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::MEDIAN',
												 'argumentCount'	=>	'1+'
												),
				'MEDIANIF'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'2+'
												),
				'MID'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::MID',
												 'argumentCount'	=>	'3'
												),
				'MIDB'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::MID',
												 'argumentCount'	=>	'3'
												),
				'MIN'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::MIN',
												 'argumentCount'	=>	'1+'
												),
				'MINA'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::MINA',
												 'argumentCount'	=>	'1+'
												),
				'MINIF'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::MINIF',
												 'argumentCount'	=>	'2+'
												),
				'MINUTE'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::MINUTEOFHOUR',
												 'argumentCount'	=>	'1'
												),
				'MINVERSE'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::MINVERSE',
												 'argumentCount'	=>	'1'
												),
				'MIRR'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::MIRR',
												 'argumentCount'	=>	'3'
												),
				'MMULT'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::MMULT',
												 'argumentCount'	=>	'2'
												),
				'MOD'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::MOD',
												 'argumentCount'	=>	'2'
												),
				'MODE'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::MODE',
												 'argumentCount'	=>	'1+'
												),
				'MONTH'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::MONTHOFYEAR',
												 'argumentCount'	=>	'1'
												),
				'MROUND'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::MROUND',
												 'argumentCount'	=>	'2'
												),
				'MULTINOMIAL'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::MULTINOMIAL',
												 'argumentCount'	=>	'1+'
												),
				'N'						=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::N',
												 'argumentCount'	=>	'1'
												),
				'NA'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::NA',
												 'argumentCount'	=>	'0'
												),
				'NEGBINOMDIST'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::NEGBINOMDIST',
												 'argumentCount'	=>	'3'
												),
				'NETWORKDAYS'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::NETWORKDAYS',
												 'argumentCount'	=>	'2+'
												),
				'NOMINAL'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::NOMINAL',
												 'argumentCount'	=>	'2'
												),
				'NORMDIST'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::NORMDIST',
												 'argumentCount'	=>	'4'
												),
				'NORMINV'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::NORMINV',
												 'argumentCount'	=>	'3'
												),
				'NORMSDIST'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::NORMSDIST',
												 'argumentCount'	=>	'1'
												),
				'NORMSINV'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::NORMSINV',
												 'argumentCount'	=>	'1'
												),
				'NOT'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOGICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Logical::NOT',
												 'argumentCount'	=>	'1'
												),
				'NOW'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::DATETIMENOW',
												 'argumentCount'	=>	'0'
												),
				'NPER'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::NPER',
												 'argumentCount'	=>	'3-5'
												),
				'NPV'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::NPV',
												 'argumentCount'	=>	'2+'
												),
				'OCT2BIN'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::OCTTOBIN',
												 'argumentCount'	=>	'1,2'
												),
				'OCT2DEC'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::OCTTODEC',
												 'argumentCount'	=>	'1'
												),
				'OCT2HEX'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_ENGINEERING,
												 'functionCall'		=>	'PHPExcel_Calculation_Engineering::OCTTOHEX',
												 'argumentCount'	=>	'1,2'
												),
				'ODD'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::ODD',
												 'argumentCount'	=>	'1'
												),
				'ODDFPRICE'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'8,9'
												),
				'ODDFYIELD'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'8,9'
												),
				'ODDLPRICE'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'7,8'
												),
				'ODDLYIELD'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'7,8'
												),
				'OFFSET'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_LookupRef::OFFSET',
												 'argumentCount'	=>	'3,5',
												 'passCellReference'=>	TRUE,
												 'passByReference'	=>	array(TRUE)
												),
				'OR'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOGICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Logical::LOGICAL_OR',
												 'argumentCount'	=>	'1+'
												),
				'PEARSON'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::CORREL',
												 'argumentCount'	=>	'2'
												),
				'PERCENTILE'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::PERCENTILE',
												 'argumentCount'	=>	'2'
												),
				'PERCENTRANK'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::PERCENTRANK',
												 'argumentCount'	=>	'2,3'
												),
				'PERMUT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::PERMUT',
												 'argumentCount'	=>	'2'
												),
				'PHONETIC'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'1'
												),
				'PI'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'pi',
												 'argumentCount'	=>	'0'
												),
				'PMT'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::PMT',
												 'argumentCount'	=>	'3-5'
												),
				'POISSON'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::POISSON',
												 'argumentCount'	=>	'3'
												),
				'POWER'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::POWER',
												 'argumentCount'	=>	'2'
												),
				'PPMT'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::PPMT',
												 'argumentCount'	=>	'4-6'
												),
				'PRICE'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::PRICE',
												 'argumentCount'	=>	'6,7'
												),
				'PRICEDISC'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::PRICEDISC',
												 'argumentCount'	=>	'4,5'
												),
				'PRICEMAT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::PRICEMAT',
												 'argumentCount'	=>	'5,6'
												),
				'PROB'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'3,4'
												),
				'PRODUCT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::PRODUCT',
												 'argumentCount'	=>	'1+'
												),
				'PROPER'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::PROPERCASE',
												 'argumentCount'	=>	'1'
												),
				'PV'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::PV',
												 'argumentCount'	=>	'3-5'
												),
				'QUARTILE'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::QUARTILE',
												 'argumentCount'	=>	'2'
												),
				'QUOTIENT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::QUOTIENT',
												 'argumentCount'	=>	'2'
												),
				'RADIANS'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'deg2rad',
												 'argumentCount'	=>	'1'
												),
				'RAND'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::RAND',
												 'argumentCount'	=>	'0'
												),
				'RANDBETWEEN'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::RAND',
												 'argumentCount'	=>	'2'
												),
				'RANK'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::RANK',
												 'argumentCount'	=>	'2,3'
												),
				'RATE'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::RATE',
												 'argumentCount'	=>	'3-6'
												),
				'RECEIVED'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::RECEIVED',
												 'argumentCount'	=>	'4-5'
												),
				'REPLACE'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::REPLACE',
												 'argumentCount'	=>	'4'
												),
				'REPLACEB'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::REPLACE',
												 'argumentCount'	=>	'4'
												),
				'REPT'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'str_repeat',
												 'argumentCount'	=>	'2'
												),
				'RIGHT'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::RIGHT',
												 'argumentCount'	=>	'1,2'
												),
				'RIGHTB'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::RIGHT',
												 'argumentCount'	=>	'1,2'
												),
				'ROMAN'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::ROMAN',
												 'argumentCount'	=>	'1,2'
												),
				'ROUND'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'round',
												 'argumentCount'	=>	'2'
												),
				'ROUNDDOWN'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::ROUNDDOWN',
												 'argumentCount'	=>	'2'
												),
				'ROUNDUP'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::ROUNDUP',
												 'argumentCount'	=>	'2'
												),
				'ROW'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_LookupRef::ROW',
												 'argumentCount'	=>	'-1',
												 'passByReference'	=>	array(TRUE)
												),
				'ROWS'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_LookupRef::ROWS',
												 'argumentCount'	=>	'1'
												),
				'RSQ'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::RSQ',
												 'argumentCount'	=>	'2'
												),
				'RTD'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'1+'
												),
				'SEARCH'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::SEARCHINSENSITIVE',
												 'argumentCount'	=>	'2,3'
												),
				'SEARCHB'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::SEARCHINSENSITIVE',
												 'argumentCount'	=>	'2,3'
												),
				'SECOND'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::SECONDOFMINUTE',
												 'argumentCount'	=>	'1'
												),
				'SERIESSUM'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::SERIESSUM',
												 'argumentCount'	=>	'4'
												),
				'SIGN'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::SIGN',
												 'argumentCount'	=>	'1'
												),
				'SIN'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'sin',
												 'argumentCount'	=>	'1'
												),
				'SINH'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'sinh',
												 'argumentCount'	=>	'1'
												),
				'SKEW'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::SKEW',
												 'argumentCount'	=>	'1+'
												),
				'SLN'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::SLN',
												 'argumentCount'	=>	'3'
												),
				'SLOPE'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::SLOPE',
												 'argumentCount'	=>	'2'
												),
				'SMALL'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::SMALL',
												 'argumentCount'	=>	'2'
												),
				'SQRT'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'sqrt',
												 'argumentCount'	=>	'1'
												),
				'SQRTPI'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::SQRTPI',
												 'argumentCount'	=>	'1'
												),
				'STANDARDIZE'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::STANDARDIZE',
												 'argumentCount'	=>	'3'
												),
				'STDEV'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::STDEV',
												 'argumentCount'	=>	'1+'
												),
				'STDEVA'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::STDEVA',
												 'argumentCount'	=>	'1+'
												),
				'STDEVP'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::STDEVP',
												 'argumentCount'	=>	'1+'
												),
				'STDEVPA'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::STDEVPA',
												 'argumentCount'	=>	'1+'
												),
				'STEYX'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::STEYX',
												 'argumentCount'	=>	'2'
												),
				'SUBSTITUTE'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::SUBSTITUTE',
												 'argumentCount'	=>	'3,4'
												),
				'SUBTOTAL'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::SUBTOTAL',
												 'argumentCount'	=>	'2+'
												),
				'SUM'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::SUM',
												 'argumentCount'	=>	'1+'
												),
				'SUMIF'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::SUMIF',
												 'argumentCount'	=>	'2,3'
												),
				'SUMIFS'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'?'
												),
				'SUMPRODUCT'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::SUMPRODUCT',
												 'argumentCount'	=>	'1+'
												),
				'SUMSQ'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::SUMSQ',
												 'argumentCount'	=>	'1+'
												),
				'SUMX2MY2'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::SUMX2MY2',
												 'argumentCount'	=>	'2'
												),
				'SUMX2PY2'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::SUMX2PY2',
												 'argumentCount'	=>	'2'
												),
				'SUMXMY2'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::SUMXMY2',
												 'argumentCount'	=>	'2'
												),
				'SYD'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::SYD',
												 'argumentCount'	=>	'4'
												),
				'T'						=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::RETURNSTRING',
												 'argumentCount'	=>	'1'
												),
				'TAN'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'tan',
												 'argumentCount'	=>	'1'
												),
				'TANH'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'tanh',
												 'argumentCount'	=>	'1'
												),
				'TBILLEQ'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::TBILLEQ',
												 'argumentCount'	=>	'3'
												),
				'TBILLPRICE'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::TBILLPRICE',
												 'argumentCount'	=>	'3'
												),
				'TBILLYIELD'			=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::TBILLYIELD',
												 'argumentCount'	=>	'3'
												),
				'TDIST'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::TDIST',
												 'argumentCount'	=>	'3'
												),
				'TEXT'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::TEXTFORMAT',
												 'argumentCount'	=>	'2'
												),
				'TIME'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::TIME',
												 'argumentCount'	=>	'3'
												),
				'TIMEVALUE'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::TIMEVALUE',
												 'argumentCount'	=>	'1'
												),
				'TINV'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::TINV',
												 'argumentCount'	=>	'2'
												),
				'TODAY'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::DATENOW',
												 'argumentCount'	=>	'0'
												),
				'TRANSPOSE'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_LookupRef::TRANSPOSE',
												 'argumentCount'	=>	'1'
												),
				'TREND'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::TREND',
												 'argumentCount'	=>	'1-4'
												),
				'TRIM'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::TRIMSPACES',
												 'argumentCount'	=>	'1'
												),
				'TRIMMEAN'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::TRIMMEAN',
												 'argumentCount'	=>	'2'
												),
				'TRUE'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOGICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Logical::TRUE',
												 'argumentCount'	=>	'0'
												),
				'TRUNC'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_MATH_AND_TRIG,
												 'functionCall'		=>	'PHPExcel_Calculation_MathTrig::TRUNC',
												 'argumentCount'	=>	'1,2'
												),
				'TTEST'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'4'
												),
				'TYPE'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::TYPE',
												 'argumentCount'	=>	'1'
												),
				'UPPER'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_TextData::UPPERCASE',
												 'argumentCount'	=>	'1'
												),
				'USDOLLAR'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'2'
												),
				'VALUE'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_TEXT_AND_DATA,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'1'
												),
				'VAR'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::VARFunc',
												 'argumentCount'	=>	'1+'
												),
				'VARA'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::VARA',
												 'argumentCount'	=>	'1+'
												),
				'VARP'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::VARP',
												 'argumentCount'	=>	'1+'
												),
				'VARPA'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::VARPA',
												 'argumentCount'	=>	'1+'
												),
				'VDB'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'5-7'
												),
				'VERSION'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_INFORMATION,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::VERSION',
												 'argumentCount'	=>	'0'
												),
				'VLOOKUP'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_LOOKUP_AND_REFERENCE,
												 'functionCall'		=>	'PHPExcel_Calculation_LookupRef::VLOOKUP',
												 'argumentCount'	=>	'3,4'
												),
				'WEEKDAY'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::DAYOFWEEK',
												 'argumentCount'	=>	'1,2'
												),
				'WEEKNUM'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::WEEKOFYEAR',
												 'argumentCount'	=>	'1,2'
												),
				'WEIBULL'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::WEIBULL',
												 'argumentCount'	=>	'4'
												),
				'WORKDAY'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::WORKDAY',
												 'argumentCount'	=>	'2+'
												),
				'XIRR'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::XIRR',
												 'argumentCount'	=>	'2,3'
												),
				'XNPV'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::XNPV',
												 'argumentCount'	=>	'3'
												),
				'YEAR'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::YEAR',
												 'argumentCount'	=>	'1'
												),
				'YEARFRAC'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_DATE_AND_TIME,
												 'functionCall'		=>	'PHPExcel_Calculation_DateTime::YEARFRAC',
												 'argumentCount'	=>	'2,3'
												),
				'YIELD'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Functions::DUMMY',
												 'argumentCount'	=>	'6,7'
												),
				'YIELDDISC'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::YIELDDISC',
												 'argumentCount'	=>	'4,5'
												),
				'YIELDMAT'				=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_FINANCIAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Financial::YIELDMAT',
												 'argumentCount'	=>	'5,6'
												),
				'ZTEST'					=> array('category'			=>	PHPExcel_Calculation_Function::CATEGORY_STATISTICAL,
												 'functionCall'		=>	'PHPExcel_Calculation_Statistical::ZTEST',
												 'argumentCount'	=>	'2-3'
												)
			);


	private static $_controlFunctions = array(
				'MKMATRIX'	=> array('argumentCount'	=>	'*',
									 'functionCall'		=>	'self::_mkMatrix'
									)
			);




	private function __construct(PHPExcel $workbook = NULL) {
		$setPrecision = (PHP_INT_SIZE == 4) ? 14 : 16;
		$this->_savedPrecision = ini_get('precision');
		if ($this->_savedPrecision < $setPrecision) {
			ini_set('precision',$setPrecision);
		}

		if ($workbook !== NULL) {
			self::$_workbookSets[$workbook->getID()] = $this;
		}

		$this->_workbook = $workbook;
		$this->_cyclicReferenceStack = new PHPExcel_CalcEngine_CyclicReferenceStack();
	    $this->_debugLog = new PHPExcel_CalcEngine_Logger($this->_cyclicReferenceStack);
	}	


	public function __destruct() {
		if ($this->_savedPrecision != ini_get('precision')) {
			ini_set('precision',$this->_savedPrecision);
		}
	}

	private static function _loadLocales() {
		$localeFileDirectory = PHPEXCEL_ROOT.'PHPExcel/locale/';
		foreach (glob($localeFileDirectory.'/*',GLOB_ONLYDIR) as $filename) {
			$filename = substr($filename,strlen($localeFileDirectory)+1);
			if ($filename != 'en') {
				self::$_validLocaleLanguages[] = $filename;
			}
		}
	}

	public static function getInstance(PHPExcel $workbook = NULL) {
		if ($workbook !== NULL) {
    		if (isset(self::$_workbookSets[$workbook->getID()])) {
    			return self::$_workbookSets[$workbook->getID()];
    		}
			return new PHPExcel_Calculation($workbook);
		}

		if (!isset(self::$_instance) || (self::$_instance === NULL)) {
			self::$_instance = new PHPExcel_Calculation();
		}

		return self::$_instance;
	}	

	public static function unsetInstance(PHPExcel $workbook = NULL) {
		if ($workbook !== NULL) {
    		if (isset(self::$_workbookSets[$workbook->getID()])) {
    			unset(self::$_workbookSets[$workbook->getID()]);
    		}
		}
    }

	public function flushInstance() {
		$this->clearCalculationCache();
	}	


	public function getDebugLog() {
		return $this->_debugLog;
	}

	public final function __clone() {
		throw new PHPExcel_Calculation_Exception ('Cloning the calculation engine is not allowed!');
	}	


	public static function getTRUE() {
		return self::$_localeBoolean['TRUE'];
	}

	public static function getFALSE() {
		return self::$_localeBoolean['FALSE'];
	}

	public static function setArrayReturnType($returnType) {
		if (($returnType == self::RETURN_ARRAY_AS_VALUE) ||
			($returnType == self::RETURN_ARRAY_AS_ERROR) ||
			($returnType == self::RETURN_ARRAY_AS_ARRAY)) {
			self::$returnArrayAsType = $returnType;
			return TRUE;
		}
		return FALSE;
	}	


	public static function getArrayReturnType() {
		return self::$returnArrayAsType;
	}	


	public function getCalculationCacheEnabled() {
		return $this->_calculationCacheEnabled;
	}	

	public function setCalculationCacheEnabled($pValue = TRUE) {
		$this->_calculationCacheEnabled = $pValue;
		$this->clearCalculationCache();
	}	


	public function enableCalculationCache() {
		$this->setCalculationCacheEnabled(TRUE);
	}	


	public function disableCalculationCache() {
		$this->setCalculationCacheEnabled(FALSE);
	}	


	public function clearCalculationCache() {
		$this->_calculationCache = array();
	}	

	public function clearCalculationCacheForWorksheet($worksheetName) {
		if (isset($this->_calculationCache[$worksheetName])) {
			unset($this->_calculationCache[$worksheetName]);
		}
	}	

	public function renameCalculationCacheForWorksheet($fromWorksheetName, $toWorksheetName) {
		if (isset($this->_calculationCache[$fromWorksheetName])) {
			$this->_calculationCache[$toWorksheetName] = &$this->_calculationCache[$fromWorksheetName];
			unset($this->_calculationCache[$fromWorksheetName]);
		}
	}	


	public function getLocale() {
		return self::$_localeLanguage;
	}	


	public function setLocale($locale = 'en_us') {
		$language = $locale = strtolower($locale);
		if (strpos($locale,'_') !== FALSE) {
			list($language) = explode('_',$locale);
		}

		if (count(self::$_validLocaleLanguages) == 1)
			self::_loadLocales();

		if (in_array($language,self::$_validLocaleLanguages)) {
			self::$_localeFunctions = array();
			self::$_localeArgumentSeparator = ',';
			self::$_localeBoolean = array('TRUE' => 'TRUE', 'FALSE' => 'FALSE', 'NULL' => 'NULL');
			if ($locale != 'en_us') {
				$functionNamesFile = PHPEXCEL_ROOT . 'PHPExcel'.DIRECTORY_SEPARATOR.'locale'.DIRECTORY_SEPARATOR.str_replace('_',DIRECTORY_SEPARATOR,$locale).DIRECTORY_SEPARATOR.'functions';
				if (!file_exists($functionNamesFile)) {
					$functionNamesFile = PHPEXCEL_ROOT . 'PHPExcel'.DIRECTORY_SEPARATOR.'locale'.DIRECTORY_SEPARATOR.$language.DIRECTORY_SEPARATOR.'functions';
					if (!file_exists($functionNamesFile)) {
						return FALSE;
					}
				}
				$localeFunctions = file($functionNamesFile,FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				foreach ($localeFunctions as $localeFunction) {
					list($localeFunction) = explode('##',$localeFunction);	
					if (strpos($localeFunction,'=') !== FALSE) {
						list($fName,$lfName) = explode('=',$localeFunction);
						$fName = trim($fName);
						$lfName = trim($lfName);
						if ((isset(self::$_PHPExcelFunctions[$fName])) && ($lfName != '') && ($fName != $lfName)) {
							self::$_localeFunctions[$fName] = $lfName;
						}
					}
				}
				if (isset(self::$_localeFunctions['TRUE'])) { self::$_localeBoolean['TRUE'] = self::$_localeFunctions['TRUE']; }
				if (isset(self::$_localeFunctions['FALSE'])) { self::$_localeBoolean['FALSE'] = self::$_localeFunctions['FALSE']; }

				$configFile = PHPEXCEL_ROOT . 'PHPExcel'.DIRECTORY_SEPARATOR.'locale'.DIRECTORY_SEPARATOR.str_replace('_',DIRECTORY_SEPARATOR,$locale).DIRECTORY_SEPARATOR.'config';
				if (!file_exists($configFile)) {
					$configFile = PHPEXCEL_ROOT . 'PHPExcel'.DIRECTORY_SEPARATOR.'locale'.DIRECTORY_SEPARATOR.$language.DIRECTORY_SEPARATOR.'config';
				}
				if (file_exists($configFile)) {
					$localeSettings = file($configFile,FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
					foreach ($localeSettings as $localeSetting) {
						list($localeSetting) = explode('##',$localeSetting);	
						if (strpos($localeSetting,'=') !== FALSE) {
							list($settingName,$settingValue) = explode('=',$localeSetting);
							$settingName = strtoupper(trim($settingName));
							switch ($settingName) {
								case 'ARGUMENTSEPARATOR' :
									self::$_localeArgumentSeparator = trim($settingValue);
									break;
							}
						}
					}
				}
			}

			self::$functionReplaceFromExcel = self::$functionReplaceToExcel =
			self::$functionReplaceFromLocale = self::$functionReplaceToLocale = NULL;
			self::$_localeLanguage = $locale;
			return TRUE;
		}
		return FALSE;
	}	



	public static function _translateSeparator($fromSeparator,$toSeparator,$formula,&$inBraces) {
		$strlen = mb_strlen($formula);
		for ($i = 0; $i < $strlen; ++$i) {
			$chr = mb_substr($formula,$i,1);
			switch ($chr) {
				case '{' :	$inBraces = TRUE;
							break;
				case '}' :	$inBraces = FALSE;
							break;
				case $fromSeparator :
							if (!$inBraces) {
								$formula = mb_substr($formula,0,$i).$toSeparator.mb_substr($formula,$i+1);
							}
			}
		}
		return $formula;
	}

	private static function _translateFormula($from,$to,$formula,$fromSeparator,$toSeparator) {
		if (self::$_localeLanguage !== 'en_us') {
			$inBraces = FALSE;
			if (strpos($formula,'"') !== FALSE) {
				$temp = explode('"',$formula);
				$i = FALSE;
				foreach($temp as &$value) {
					if ($i = !$i) {
						$value = preg_replace($from,$to,$value);
						$value = self::_translateSeparator($fromSeparator,$toSeparator,$value,$inBraces);
					}
				}
				unset($value);
				$formula = implode('"',$temp);
			} else {
				$formula = preg_replace($from,$to,$formula);
				$formula = self::_translateSeparator($fromSeparator,$toSeparator,$formula,$inBraces);
			}
		}

		return $formula;
	}

	private static $functionReplaceFromExcel	= NULL;
	private static $functionReplaceToLocale		= NULL;

	public function _translateFormulaToLocale($formula) {
		if (self::$functionReplaceFromExcel === NULL) {
			self::$functionReplaceFromExcel = array();
			foreach(array_keys(self::$_localeFunctions) as $excelFunctionName) {
				self::$functionReplaceFromExcel[] = '/(@?[^\w\.])'.preg_quote($excelFunctionName).'([\s]*\()/Ui';
			}
			foreach(array_keys(self::$_localeBoolean) as $excelBoolean) {
				self::$functionReplaceFromExcel[] = '/(@?[^\w\.])'.preg_quote($excelBoolean).'([^\w\.])/Ui';
			}

		}

		if (self::$functionReplaceToLocale === NULL) {
			self::$functionReplaceToLocale = array();
			foreach(array_values(self::$_localeFunctions) as $localeFunctionName) {
				self::$functionReplaceToLocale[] = '$1'.trim($localeFunctionName).'$2';
			}
			foreach(array_values(self::$_localeBoolean) as $localeBoolean) {
				self::$functionReplaceToLocale[] = '$1'.trim($localeBoolean).'$2';
			}
		}

		return self::_translateFormula(self::$functionReplaceFromExcel,self::$functionReplaceToLocale,$formula,',',self::$_localeArgumentSeparator);
	}	


	private static $functionReplaceFromLocale	= NULL;
	private static $functionReplaceToExcel		= NULL;

	public function _translateFormulaToEnglish($formula) {
		if (self::$functionReplaceFromLocale === NULL) {
			self::$functionReplaceFromLocale = array();
			foreach(array_values(self::$_localeFunctions) as $localeFunctionName) {
				self::$functionReplaceFromLocale[] = '/(@?[^\w\.])'.preg_quote($localeFunctionName).'([\s]*\()/Ui';
			}
			foreach(array_values(self::$_localeBoolean) as $excelBoolean) {
				self::$functionReplaceFromLocale[] = '/(@?[^\w\.])'.preg_quote($excelBoolean).'([^\w\.])/Ui';
			}
		}

		if (self::$functionReplaceToExcel === NULL) {
			self::$functionReplaceToExcel = array();
			foreach(array_keys(self::$_localeFunctions) as $excelFunctionName) {
				self::$functionReplaceToExcel[] = '$1'.trim($excelFunctionName).'$2';
			}
			foreach(array_keys(self::$_localeBoolean) as $excelBoolean) {
				self::$functionReplaceToExcel[] = '$1'.trim($excelBoolean).'$2';
			}
		}

		return self::_translateFormula(self::$functionReplaceFromLocale,self::$functionReplaceToExcel,$formula,self::$_localeArgumentSeparator,',');
	}	


	public static function _localeFunc($function) {
		if (self::$_localeLanguage !== 'en_us') {
			$functionName = trim($function,'(');
			if (isset(self::$_localeFunctions[$functionName])) {
				$brace = ($functionName != $function);
				$function = self::$_localeFunctions[$functionName];
				if ($brace) { $function .= '('; }
			}
		}
		return $function;
	}




	public static function _wrapResult($value) {
		if (is_string($value)) {
			if (preg_match('/^'.self::CALCULATION_REGEXP_ERROR.'$/i', $value, $match)) {
				return $value;
			}
			return '"'.$value.'"';
		} else if((is_float($value)) && ((is_nan($value)) || (is_infinite($value)))) {
			return PHPExcel_Calculation_Functions::NaN();
		}

		return $value;
	}	


	public static function _unwrapResult($value) {
		if (is_string($value)) {
			if ((isset($value[0])) && ($value[0] == '"') && (substr($value,-1) == '"')) {
				return substr($value,1,-1);
			}
		} else if((is_float($value)) && ((is_nan($value)) || (is_infinite($value)))) {
			return PHPExcel_Calculation_Functions::NaN();
		}
		return $value;
	}	




	public function calculate(PHPExcel_Cell $pCell = NULL) {
		try {
			return $this->calculateCellValue($pCell);
		} catch (PHPExcel_Exception $e) {
			throw new PHPExcel_Calculation_Exception($e->getMessage());
		}
	}	


	public function calculateCellValue(PHPExcel_Cell $pCell = NULL, $resetLog = TRUE) {
		if ($pCell === NULL) {
			return NULL;
		}

		$returnArrayAsType = self::$returnArrayAsType;
		if ($resetLog) {
			$this->formulaError = null;
			$this->_debugLog->clearLog();
			$this->_cyclicReferenceStack->clear();
			$this->_cyclicFormulaCount = 1;

			self::$returnArrayAsType = self::RETURN_ARRAY_AS_ARRAY;
		}

        $this->_cellStack[] = array(
            'sheet' => $pCell->getWorksheet()->getTitle(),
            'cell' => $pCell->getCoordinate(),
        );
		try {
			$result = self::_unwrapResult($this->_calculateFormulaValue($pCell->getValue(), $pCell->getCoordinate(), $pCell));
            $cellAddress = array_pop($this->_cellStack);
            $this->_workbook->getSheetByName($cellAddress['sheet'])->getCell($cellAddress['cell']);
		} catch (PHPExcel_Exception $e) {
            $cellAddress = array_pop($this->_cellStack);
            $this->_workbook->getSheetByName($cellAddress['sheet'])->getCell($cellAddress['cell']);
			throw new PHPExcel_Calculation_Exception($e->getMessage());
		}

		if ((is_array($result)) && (self::$returnArrayAsType != self::RETURN_ARRAY_AS_ARRAY)) {
			self::$returnArrayAsType = $returnArrayAsType;
			$testResult = PHPExcel_Calculation_Functions::flattenArray($result);
			if (self::$returnArrayAsType == self::RETURN_ARRAY_AS_ERROR) {
				return PHPExcel_Calculation_Functions::VALUE();
			}
			if (count($testResult) != 1) {
				$r = array_keys($result);
				$r = array_shift($r);
				if (!is_numeric($r)) { return PHPExcel_Calculation_Functions::VALUE(); }
				if (is_array($result[$r])) {
					$c = array_keys($result[$r]);
					$c = array_shift($c);
					if (!is_numeric($c)) {
						return PHPExcel_Calculation_Functions::VALUE();
					}
				}
			}
			$result = array_shift($testResult);
		}
		self::$returnArrayAsType = $returnArrayAsType;


		if ($result === NULL) {
			return 0;
		} elseif((is_float($result)) && ((is_nan($result)) || (is_infinite($result)))) {
			return PHPExcel_Calculation_Functions::NaN();
		}
		return $result;
	}	


	public function parseFormula($formula) {
		$formula = trim($formula);
		if ((!isset($formula[0])) || ($formula[0] != '=')) return array();
		$formula = ltrim(substr($formula,1));
		if (!isset($formula[0])) return array();

		return $this->_parseFormula($formula);
	}	


	public function calculateFormula($formula, $cellID=NULL, PHPExcel_Cell $pCell = NULL) {
		$this->formulaError = null;
		$this->_debugLog->clearLog();
		$this->_cyclicReferenceStack->clear();

		$resetCache = $this->getCalculationCacheEnabled();
		$this->_calculationCacheEnabled = FALSE;
		try {
			$result = self::_unwrapResult($this->_calculateFormulaValue($formula, $cellID, $pCell));
		} catch (PHPExcel_Exception $e) {
			throw new PHPExcel_Calculation_Exception($e->getMessage());
		}

		$this->_calculationCacheEnabled = $resetCache;

		return $result;
	}	


    public function getValueFromCache($worksheetName, $cellID, &$cellValue) {
		$this->_debugLog->writeDebugLog('Testing cache value for cell ', $worksheetName, '!', $cellID);
		if (($this->_calculationCacheEnabled) && (isset($this->_calculationCache[$worksheetName][$cellID]))) {
			$this->_debugLog->writeDebugLog('Retrieving value for cell ', $worksheetName, '!', $cellID, ' from cache');
			$cellValue = $this->_calculationCache[$worksheetName][$cellID];
			return TRUE;
		}
		return FALSE;
    }

    public function saveValueToCache($worksheetName, $cellID, $cellValue) {
		if ($this->_calculationCacheEnabled) {
			$this->_calculationCache[$worksheetName][$cellID] = $cellValue;
		}
	}

	public function _calculateFormulaValue($formula, $cellID=null, PHPExcel_Cell $pCell = null) {
		$cellValue = '';

		$formula = trim($formula);
		if ($formula[0] != '=') return self::_wrapResult($formula);
		$formula = ltrim(substr($formula,1));
		if (!isset($formula[0])) return self::_wrapResult($formula);

		$pCellParent = ($pCell !== NULL) ? $pCell->getWorksheet() : NULL;
		$wsTitle = ($pCellParent !== NULL) ? $pCellParent->getTitle() : "\x00Wrk";

		if (($cellID !== NULL) && ($this->getValueFromCache($wsTitle, $cellID, $cellValue))) {
			return $cellValue;
		}

		if (($wsTitle[0] !== "\x00") && ($this->_cyclicReferenceStack->onStack($wsTitle.'!'.$cellID))) {
			if ($this->cyclicFormulaCount <= 0) {
				return $this->_raiseFormulaError('Cyclic Reference in Formula');
			} elseif (($this->_cyclicFormulaCount >= $this->cyclicFormulaCount) &&
					  ($this->_cyclicFormulaCell == $wsTitle.'!'.$cellID)) {
				return $cellValue;
			} elseif ($this->_cyclicFormulaCell == $wsTitle.'!'.$cellID) {
				++$this->_cyclicFormulaCount;
				if ($this->_cyclicFormulaCount >= $this->cyclicFormulaCount) {
					return $cellValue;
				}
			} elseif ($this->_cyclicFormulaCell == '') {
				$this->_cyclicFormulaCell = $wsTitle.'!'.$cellID;
				if ($this->_cyclicFormulaCount >= $this->cyclicFormulaCount) {
					return $cellValue;
				}
			}
		}

		$this->_cyclicReferenceStack->push($wsTitle.'!'.$cellID);
		$cellValue = $this->_processTokenStack($this->_parseFormula($formula, $pCell), $cellID, $pCell);
		$this->_cyclicReferenceStack->pop();

		if ($cellID !== NULL) {
			$this->saveValueToCache($wsTitle, $cellID, $cellValue);
		}

		return $cellValue;
	}	


	private static function _checkMatrixOperands(&$operand1,&$operand2,$resize = 1) {
		if (!is_array($operand1)) {
			list($matrixRows,$matrixColumns) = self::_getMatrixDimensions($operand2);
			$operand1 = array_fill(0,$matrixRows,array_fill(0,$matrixColumns,$operand1));
			$resize = 0;
		} elseif (!is_array($operand2)) {
			list($matrixRows,$matrixColumns) = self::_getMatrixDimensions($operand1);
			$operand2 = array_fill(0,$matrixRows,array_fill(0,$matrixColumns,$operand2));
			$resize = 0;
		}

		list($matrix1Rows,$matrix1Columns) = self::_getMatrixDimensions($operand1);
		list($matrix2Rows,$matrix2Columns) = self::_getMatrixDimensions($operand2);
		if (($matrix1Rows == $matrix2Columns) && ($matrix2Rows == $matrix1Columns)) {
			$resize = 1;
		}

		if ($resize == 2) {
			self::_resizeMatricesExtend($operand1,$operand2,$matrix1Rows,$matrix1Columns,$matrix2Rows,$matrix2Columns);
		} elseif ($resize == 1) {
			self::_resizeMatricesShrink($operand1,$operand2,$matrix1Rows,$matrix1Columns,$matrix2Rows,$matrix2Columns);
		}
		return array( $matrix1Rows,$matrix1Columns,$matrix2Rows,$matrix2Columns);
	}	


	public static function _getMatrixDimensions(&$matrix) {
		$matrixRows = count($matrix);
		$matrixColumns = 0;
		foreach($matrix as $rowKey => $rowValue) {
			$matrixColumns = max(count($rowValue),$matrixColumns);
			if (!is_array($rowValue)) {
				$matrix[$rowKey] = array($rowValue);
			} else {
				$matrix[$rowKey] = array_values($rowValue);
			}
		}
		$matrix = array_values($matrix);
		return array($matrixRows,$matrixColumns);
	}	


	private static function _resizeMatricesShrink(&$matrix1,&$matrix2,$matrix1Rows,$matrix1Columns,$matrix2Rows,$matrix2Columns) {
		if (($matrix2Columns < $matrix1Columns) || ($matrix2Rows < $matrix1Rows)) {
			if ($matrix2Rows < $matrix1Rows) {
				for ($i = $matrix2Rows; $i < $matrix1Rows; ++$i) {
					unset($matrix1[$i]);
				}
			}
			if ($matrix2Columns < $matrix1Columns) {
				for ($i = 0; $i < $matrix1Rows; ++$i) {
					for ($j = $matrix2Columns; $j < $matrix1Columns; ++$j) {
						unset($matrix1[$i][$j]);
					}
				}
			}
		}

		if (($matrix1Columns < $matrix2Columns) || ($matrix1Rows < $matrix2Rows)) {
			if ($matrix1Rows < $matrix2Rows) {
				for ($i = $matrix1Rows; $i < $matrix2Rows; ++$i) {
					unset($matrix2[$i]);
				}
			}
			if ($matrix1Columns < $matrix2Columns) {
				for ($i = 0; $i < $matrix2Rows; ++$i) {
					for ($j = $matrix1Columns; $j < $matrix2Columns; ++$j) {
						unset($matrix2[$i][$j]);
					}
				}
			}
		}
	}	


	private static function _resizeMatricesExtend(&$matrix1,&$matrix2,$matrix1Rows,$matrix1Columns,$matrix2Rows,$matrix2Columns) {
		if (($matrix2Columns < $matrix1Columns) || ($matrix2Rows < $matrix1Rows)) {
			if ($matrix2Columns < $matrix1Columns) {
				for ($i = 0; $i < $matrix2Rows; ++$i) {
					$x = $matrix2[$i][$matrix2Columns-1];
					for ($j = $matrix2Columns; $j < $matrix1Columns; ++$j) {
						$matrix2[$i][$j] = $x;
					}
				}
			}
			if ($matrix2Rows < $matrix1Rows) {
				$x = $matrix2[$matrix2Rows-1];
				for ($i = 0; $i < $matrix1Rows; ++$i) {
					$matrix2[$i] = $x;
				}
			}
		}

		if (($matrix1Columns < $matrix2Columns) || ($matrix1Rows < $matrix2Rows)) {
			if ($matrix1Columns < $matrix2Columns) {
				for ($i = 0; $i < $matrix1Rows; ++$i) {
					$x = $matrix1[$i][$matrix1Columns-1];
					for ($j = $matrix1Columns; $j < $matrix2Columns; ++$j) {
						$matrix1[$i][$j] = $x;
					}
				}
			}
			if ($matrix1Rows < $matrix2Rows) {
				$x = $matrix1[$matrix1Rows-1];
				for ($i = 0; $i < $matrix2Rows; ++$i) {
					$matrix1[$i] = $x;
				}
			}
		}
	}	


	private function _showValue($value) {
		if ($this->_debugLog->getWriteDebugLog()) {
			$testArray = PHPExcel_Calculation_Functions::flattenArray($value);
			if (count($testArray) == 1) {
				$value = array_pop($testArray);
			}

			if (is_array($value)) {
				$returnMatrix = array();
				$pad = $rpad = ', ';
				foreach($value as $row) {
					if (is_array($row)) {
						$returnMatrix[] = implode($pad,array_map(array($this,'_showValue'),$row));
						$rpad = '; ';
					} else {
						$returnMatrix[] = $this->_showValue($row);
					}
				}
				return '{ '.implode($rpad,$returnMatrix).' }';
			} elseif(is_string($value) && (trim($value,'"') == $value)) {
				return '"'.$value.'"';
			} elseif(is_bool($value)) {
				return ($value) ? self::$_localeBoolean['TRUE'] : self::$_localeBoolean['FALSE'];
			}
		}
		return PHPExcel_Calculation_Functions::flattenSingleValue($value);
	}	


	private function _showTypeDetails($value) {
		if ($this->_debugLog->getWriteDebugLog()) {
			$testArray = PHPExcel_Calculation_Functions::flattenArray($value);
			if (count($testArray) == 1) {
				$value = array_pop($testArray);
			}

			if ($value === NULL) {
				return 'a NULL value';
			} elseif (is_float($value)) {
				$typeString = 'a floating point number';
			} elseif(is_int($value)) {
				$typeString = 'an integer number';
			} elseif(is_bool($value)) {
				$typeString = 'a boolean';
			} elseif(is_array($value)) {
				$typeString = 'a matrix';
			} else {
				if ($value == '') {
					return 'an empty string';
				} elseif ($value[0] == '#') {
					return 'a '.$value.' error';
				} else {
					$typeString = 'a string';
				}
			}
			return $typeString.' with a value of '.$this->_showValue($value);
		}
	}	


	private function _convertMatrixReferences($formula) {
		static $matrixReplaceFrom = array('{',';','}');
		static $matrixReplaceTo = array('MKMATRIX(MKMATRIX(','),MKMATRIX(','))');

		if (strpos($formula,'{') !== FALSE) {
			if (strpos($formula,'"') !== FALSE) {
				$temp = explode('"',$formula);
				$openCount = $closeCount = 0;
				$i = FALSE;
				foreach($temp as &$value) {
					if ($i = !$i) {
						$openCount += substr_count($value,'{');
						$closeCount += substr_count($value,'}');
						$value = str_replace($matrixReplaceFrom,$matrixReplaceTo,$value);
					}
				}
				unset($value);
				$formula = implode('"',$temp);
			} else {
				$openCount = substr_count($formula,'{');
				$closeCount = substr_count($formula,'}');
				$formula = str_replace($matrixReplaceFrom,$matrixReplaceTo,$formula);
			}
			if ($openCount < $closeCount) {
				if ($openCount > 0) {
					return $this->_raiseFormulaError("Formula Error: Mismatched matrix braces '}'");
				} else {
					return $this->_raiseFormulaError("Formula Error: Unexpected '}' encountered");
				}
			} elseif ($openCount > $closeCount) {
				if ($closeCount > 0) {
					return $this->_raiseFormulaError("Formula Error: Mismatched matrix braces '{'");
				} else {
					return $this->_raiseFormulaError("Formula Error: Unexpected '{' encountered");
				}
			}
		}

		return $formula;
	}	


	private static function _mkMatrix() {
		return func_get_args();
	}	


	private static $_operatorAssociativity	= array(
		'^' => 0,															
		'*' => 0, '/' => 0, 												
		'+' => 0, '-' => 0,													
		'&' => 0,															
		'|' => 0, ':' => 0,													
		'>' => 0, '<' => 0, '=' => 0, '>=' => 0, '<=' => 0, '<>' => 0		
	);

	private static $_comparisonOperators	= array('>' => TRUE, '<' => TRUE, '=' => TRUE, '>=' => TRUE, '<=' => TRUE, '<>' => TRUE);

	private static $_operatorPrecedence	= array(
		':' => 8,																
		'|' => 7,																
		'~' => 6,																
		'%' => 5,																
		'^' => 4,																
		'*' => 3, '/' => 3, 													
		'+' => 2, '-' => 2,														
		'&' => 1,																
		'>' => 0, '<' => 0, '=' => 0, '>=' => 0, '<=' => 0, '<>' => 0			
	);

	private function _parseFormula($formula, PHPExcel_Cell $pCell = NULL) {
		if (($formula = $this->_convertMatrixReferences(trim($formula))) === FALSE) {
			return FALSE;
		}

		$pCellParent = ($pCell !== NULL) ? $pCell->getWorksheet() : NULL;

		$regexpMatchString = '/^('.self::CALCULATION_REGEXP_FUNCTION.
							   '|'.self::CALCULATION_REGEXP_CELLREF.
							   '|'.self::CALCULATION_REGEXP_NUMBER.
							   '|'.self::CALCULATION_REGEXP_STRING.
							   '|'.self::CALCULATION_REGEXP_OPENBRACE.
							   '|'.self::CALCULATION_REGEXP_NAMEDRANGE.
							   '|'.self::CALCULATION_REGEXP_ERROR.
							 ')/si';

		$index = 0;
		$stack = new PHPExcel_Calculation_Token_Stack;
		$output = array();
		$expectingOperator = FALSE;					
		$expectingOperand = FALSE;					
		while(TRUE) {
			$opCharacter = $formula[$index];    
			if ((isset(self::$_comparisonOperators[$opCharacter])) && (strlen($formula) > $index) && (isset(self::$_comparisonOperators[$formula[$index+1]]))) {
				$opCharacter .= $formula[++$index];
			}

			$isOperandOrFunction = preg_match($regexpMatchString, substr($formula, $index), $match);

			if ($opCharacter == '-' && !$expectingOperator) {				
				$stack->push('Unary Operator','~');							
				++$index;													
			} elseif ($opCharacter == '%' && $expectingOperator) {
				$stack->push('Unary Operator','%');							
				++$index;
			} elseif ($opCharacter == '+' && !$expectingOperator) {			
				++$index;													
			} elseif ((($opCharacter == '~') || ($opCharacter == '|')) && (!$isOperandOrFunction)) {	
				return $this->_raiseFormulaError("Formula Error: Illegal character '~'");				

			} elseif ((isset(self::$_operators[$opCharacter]) or $isOperandOrFunction) && $expectingOperator) {	
				while($stack->count() > 0 &&
					($o2 = $stack->last()) &&
					isset(self::$_operators[$o2['value']]) &&
					@(self::$_operatorAssociativity[$opCharacter] ? self::$_operatorPrecedence[$opCharacter] < self::$_operatorPrecedence[$o2['value']] : self::$_operatorPrecedence[$opCharacter] <= self::$_operatorPrecedence[$o2['value']])) {
					$output[] = $stack->pop();								
				}
				$stack->push('Binary Operator',$opCharacter);	
				++$index;
				$expectingOperator = FALSE;

			} elseif ($opCharacter == ')' && $expectingOperator) {			
				$expectingOperand = FALSE;
				while (($o2 = $stack->pop()) && $o2['value'] != '(') {		
					if ($o2 === NULL) return $this->_raiseFormulaError('Formula Error: Unexpected closing brace ")"');
					else $output[] = $o2;
				}
				$d = $stack->last(2);
				if (preg_match('/^'.self::CALCULATION_REGEXP_FUNCTION.'$/i', $d['value'], $matches)) {	
					$functionName = $matches[1];										
					$d = $stack->pop();
					$argumentCount = $d['value'];		
					$output[] = $d;						
					$output[] = $stack->pop();			
					if (isset(self::$_controlFunctions[$functionName])) {
						$expectedArgumentCount = self::$_controlFunctions[$functionName]['argumentCount'];
						$functionCall = self::$_controlFunctions[$functionName]['functionCall'];
					} elseif (isset(self::$_PHPExcelFunctions[$functionName])) {
						$expectedArgumentCount = self::$_PHPExcelFunctions[$functionName]['argumentCount'];
						$functionCall = self::$_PHPExcelFunctions[$functionName]['functionCall'];
					} else {	
						return $this->_raiseFormulaError("Formula Error: Internal error, non-function on stack");
					}
					$argumentCountError = FALSE;
					if (is_numeric($expectedArgumentCount)) {
						if ($expectedArgumentCount < 0) {
							if ($argumentCount > abs($expectedArgumentCount)) {
								$argumentCountError = TRUE;
								$expectedArgumentCountString = 'no more than '.abs($expectedArgumentCount);
							}
						} else {
							if ($argumentCount != $expectedArgumentCount) {
								$argumentCountError = TRUE;
								$expectedArgumentCountString = $expectedArgumentCount;
							}
						}
					} elseif ($expectedArgumentCount != '*') {
						$isOperandOrFunction = preg_match('/(\d*)([-+,])(\d*)/',$expectedArgumentCount,$argMatch);
						switch ($argMatch[2]) {
							case '+' :
								if ($argumentCount < $argMatch[1]) {
									$argumentCountError = TRUE;
									$expectedArgumentCountString = $argMatch[1].' or more ';
								}
								break;
							case '-' :
								if (($argumentCount < $argMatch[1]) || ($argumentCount > $argMatch[3])) {
									$argumentCountError = TRUE;
									$expectedArgumentCountString = 'between '.$argMatch[1].' and '.$argMatch[3];
								}
								break;
							case ',' :
								if (($argumentCount != $argMatch[1]) && ($argumentCount != $argMatch[3])) {
									$argumentCountError = TRUE;
									$expectedArgumentCountString = 'either '.$argMatch[1].' or '.$argMatch[3];
								}
								break;
						}
					}
					if ($argumentCountError) {
						return $this->_raiseFormulaError("Formula Error: Wrong number of arguments for $functionName() function: $argumentCount given, ".$expectedArgumentCountString." expected");
					}
				}
				++$index;

			} elseif ($opCharacter == ',') {			
				while (($o2 = $stack->pop()) && $o2['value'] != '(') {		
					if ($o2 === NULL) return $this->_raiseFormulaError("Formula Error: Unexpected ,");
					else $output[] = $o2;	
				}
				if (($expectingOperand) || (!$expectingOperator)) {
					$output[] = array('type' => 'NULL Value', 'value' => self::$_ExcelConstants['NULL'], 'reference' => NULL);
				}
				$d = $stack->last(2);
				if (!preg_match('/^'.self::CALCULATION_REGEXP_FUNCTION.'$/i', $d['value'], $matches))
					return $this->_raiseFormulaError("Formula Error: Unexpected ,");
				$d = $stack->pop();
				$stack->push($d['type'],++$d['value'],$d['reference']);	
				$stack->push('Brace', '(');	
				$expectingOperator = FALSE;
				$expectingOperand = TRUE;
				++$index;

			} elseif ($opCharacter == '(' && !$expectingOperator) {
				$stack->push('Brace', '(');
				++$index;

			} elseif ($isOperandOrFunction && !$expectingOperator) {	
				$expectingOperator = TRUE;
				$expectingOperand = FALSE;
				$val = $match[1];
				$length = strlen($val);

				if (preg_match('/^'.self::CALCULATION_REGEXP_FUNCTION.'$/i', $val, $matches)) {
					$val = preg_replace('/\s/','',$val);
					if (isset(self::$_PHPExcelFunctions[strtoupper($matches[1])]) || isset(self::$_controlFunctions[strtoupper($matches[1])])) {	
						$stack->push('Function', strtoupper($val));
						$ax = preg_match('/^\s*(\s*\))/i', substr($formula, $index+$length), $amatch);
						if ($ax) {
							$stack->push('Operand Count for Function '.strtoupper($val).')', 0);
							$expectingOperator = TRUE;
						} else {
							$stack->push('Operand Count for Function '.strtoupper($val).')', 1);
							$expectingOperator = FALSE;
						}
						$stack->push('Brace', '(');
					} else {	
						$output[] = array('type' => 'Value', 'value' => $matches[1], 'reference' => NULL);
					}
				} elseif (preg_match('/^'.self::CALCULATION_REGEXP_CELLREF.'$/i', $val, $matches)) {

					$testPrevOp = $stack->last(1);
					if ($testPrevOp['value'] == ':') {
						if ($matches[2] == '') {
							$startCellRef = $output[count($output)-1]['value'];
							preg_match('/^'.self::CALCULATION_REGEXP_CELLREF.'$/i', $startCellRef, $startMatches);
							if ($startMatches[2] > '') {
								$val = $startMatches[2].'!'.$val;
							}
						} else {
							return $this->_raiseFormulaError("3D Range references are not yet supported");
						}
					}

					$output[] = array('type' => 'Cell Reference', 'value' => $val, 'reference' => $val);
				} else {	
					$testPrevOp = $stack->last(1);
					if ($testPrevOp['value'] == ':') {
						$startRowColRef = $output[count($output)-1]['value'];
						$rangeWS1 = '';
						if (strpos('!',$startRowColRef) !== FALSE) {
							list($rangeWS1,$startRowColRef) = explode('!',$startRowColRef);
						}
						if ($rangeWS1 != '') $rangeWS1 .= '!';
						$rangeWS2 = $rangeWS1;
						if (strpos('!',$val) !== FALSE) {
							list($rangeWS2,$val) = explode('!',$val);
						}
						if ($rangeWS2 != '') $rangeWS2 .= '!';
						if ((is_integer($startRowColRef)) && (ctype_digit($val)) &&
							($startRowColRef <= 1048576) && ($val <= 1048576)) {
							$endRowColRef = ($pCellParent !== NULL) ? $pCellParent->getHighestColumn() : 'XFD';	
							$output[count($output)-1]['value'] = $rangeWS1.'A'.$startRowColRef;
							$val = $rangeWS2.$endRowColRef.$val;
						} elseif ((ctype_alpha($startRowColRef)) && (ctype_alpha($val)) &&
							(strlen($startRowColRef) <= 3) && (strlen($val) <= 3)) {
							$endRowColRef = ($pCellParent !== NULL) ? $pCellParent->getHighestRow() : 1048576;		
							$output[count($output)-1]['value'] = $rangeWS1.strtoupper($startRowColRef).'1';
							$val = $rangeWS2.$val.$endRowColRef;
						}
					}

					$localeConstant = FALSE;
					if ($opCharacter == '"') {
						$val = self::_wrapResult(str_replace('""','"',self::_unwrapResult($val)));
					} elseif (is_numeric($val)) {
						if ((strpos($val,'.') !== FALSE) || (stripos($val,'e') !== FALSE) || ($val > PHP_INT_MAX) || ($val < -PHP_INT_MAX)) {
							$val = (float) $val;
						} else {
							$val = (integer) $val;
						}
					} elseif (isset(self::$_ExcelConstants[trim(strtoupper($val))])) {
						$excelConstant = trim(strtoupper($val));
						$val = self::$_ExcelConstants[$excelConstant];
					} elseif (($localeConstant = array_search(trim(strtoupper($val)), self::$_localeBoolean)) !== FALSE) {
						$val = self::$_ExcelConstants[$localeConstant];
					}
					$details = array('type' => 'Value', 'value' => $val, 'reference' => NULL);
					if ($localeConstant) { $details['localeValue'] = $localeConstant; }
					$output[] = $details;
				}
				$index += $length;

			} elseif ($opCharacter == '$') {	
				++$index;
			} elseif ($opCharacter == ')') {	
				if ($expectingOperand) {
					$output[] = array('type' => 'NULL Value', 'value' => self::$_ExcelConstants['NULL'], 'reference' => NULL);
					$expectingOperand = FALSE;
					$expectingOperator = TRUE;
				} else {
					return $this->_raiseFormulaError("Formula Error: Unexpected ')'");
				}
			} elseif (isset(self::$_operators[$opCharacter]) && !$expectingOperator) {
				return $this->_raiseFormulaError("Formula Error: Unexpected operator '$opCharacter'");
			} else {	
				return $this->_raiseFormulaError("Formula Error: An unexpected error occured");
			}
			if ($index == strlen($formula)) {
				if ((isset(self::$_operators[$opCharacter])) && ($opCharacter != '%')) {
					return $this->_raiseFormulaError("Formula Error: Operator '$opCharacter' has no operands");
				} else {
					break;
				}
			}
			while (($formula[$index] == "\n") || ($formula[$index] == "\r")) {
				++$index;
			}
			if ($formula[$index] == ' ') {
				while ($formula[$index] == ' ') {
					++$index;
				}
				if (($expectingOperator) && (preg_match('/^'.self::CALCULATION_REGEXP_CELLREF.'.*/Ui', substr($formula, $index), $match)) &&
					($output[count($output)-1]['type'] == 'Cell Reference')) {
					while($stack->count() > 0 &&
						($o2 = $stack->last()) &&
						isset(self::$_operators[$o2['value']]) &&
						@(self::$_operatorAssociativity[$opCharacter] ? self::$_operatorPrecedence[$opCharacter] < self::$_operatorPrecedence[$o2['value']] : self::$_operatorPrecedence[$opCharacter] <= self::$_operatorPrecedence[$o2['value']])) {
						$output[] = $stack->pop();								
					}
					$stack->push('Binary Operator','|');	
					$expectingOperator = FALSE;
				}
			}
		}

		while (($op = $stack->pop()) !== NULL) {	
			if ((is_array($op) && $op['value'] == '(') || ($op === '('))
				return $this->_raiseFormulaError("Formula Error: Expecting ')'");	
			$output[] = $op;
		}
		return $output;
	}	


	private static function _dataTestReference(&$operandData)
	{
		$operand = $operandData['value'];
		if (($operandData['reference'] === NULL) && (is_array($operand))) {
			$rKeys = array_keys($operand);
			$rowKey = array_shift($rKeys);
			$cKeys = array_keys(array_keys($operand[$rowKey]));
			$colKey = array_shift($cKeys);
			if (ctype_upper($colKey)) {
				$operandData['reference'] = $colKey.$rowKey;
			}
		}
		return $operand;
	}

	private function _processTokenStack($tokens, $cellID = NULL, PHPExcel_Cell $pCell = NULL) {
		if ($tokens == FALSE) return FALSE;

		$pCellWorksheet = ($pCell !== NULL) ? $pCell->getWorksheet() : NULL;
		$pCellParent = ($pCell !== NULL) ? $pCell->getParent() : null;
		$stack = new PHPExcel_Calculation_Token_Stack;

		foreach ($tokens as $tokenData) {
			$token = $tokenData['value'];
			if (isset(self::$_binaryOperators[$token])) {
				if (($operand2Data = $stack->pop()) === NULL) return $this->_raiseFormulaError('Internal error - Operand value missing from stack');
				if (($operand1Data = $stack->pop()) === NULL) return $this->_raiseFormulaError('Internal error - Operand value missing from stack');

				$operand1 = self::_dataTestReference($operand1Data);
				$operand2 = self::_dataTestReference($operand2Data);

				if ($token == ':') {
					$this->_debugLog->writeDebugLog('Evaluating Range ', $this->_showValue($operand1Data['reference']), ' ', $token, ' ', $this->_showValue($operand2Data['reference']));
				} else {
					$this->_debugLog->writeDebugLog('Evaluating ', $this->_showValue($operand1), ' ', $token, ' ', $this->_showValue($operand2));
				}

				switch ($token) {
					case '>'	:			
					case '<'	:			
					case '>='	:			
					case '<='	:			
					case '='	:			
					case '<>'	:			
						$this->_executeBinaryComparisonOperation($cellID,$operand1,$operand2,$token,$stack);
						break;
					case ':'	:			
						$sheet1 = $sheet2 = '';
						if (strpos($operand1Data['reference'],'!') !== FALSE) {
							list($sheet1,$operand1Data['reference']) = explode('!',$operand1Data['reference']);
						} else {
							$sheet1 = ($pCellParent !== NULL) ? $pCellWorksheet->getTitle() : '';
						}
						if (strpos($operand2Data['reference'],'!') !== FALSE) {
							list($sheet2,$operand2Data['reference']) = explode('!',$operand2Data['reference']);
						} else {
							$sheet2 = $sheet1;
						}
						if ($sheet1 == $sheet2) {
							if ($operand1Data['reference'] === NULL) {
								if ((trim($operand1Data['value']) != '') && (is_numeric($operand1Data['value']))) {
									$operand1Data['reference'] = $pCell->getColumn().$operand1Data['value'];
								} elseif (trim($operand1Data['reference']) == '') {
									$operand1Data['reference'] = $pCell->getCoordinate();
								} else {
									$operand1Data['reference'] = $operand1Data['value'].$pCell->getRow();
								}
							}
							if ($operand2Data['reference'] === NULL) {
								if ((trim($operand2Data['value']) != '') && (is_numeric($operand2Data['value']))) {
									$operand2Data['reference'] = $pCell->getColumn().$operand2Data['value'];
								} elseif (trim($operand2Data['reference']) == '') {
									$operand2Data['reference'] = $pCell->getCoordinate();
								} else {
									$operand2Data['reference'] = $operand2Data['value'].$pCell->getRow();
								}
							}

							$oData = array_merge(explode(':',$operand1Data['reference']),explode(':',$operand2Data['reference']));
							$oCol = $oRow = array();
							foreach($oData as $oDatum) {
								$oCR = PHPExcel_Cell::coordinateFromString($oDatum);
								$oCol[] = PHPExcel_Cell::columnIndexFromString($oCR[0]) - 1;
								$oRow[] = $oCR[1];
							}
							$cellRef = PHPExcel_Cell::stringFromColumnIndex(min($oCol)).min($oRow).':'.PHPExcel_Cell::stringFromColumnIndex(max($oCol)).max($oRow);
							if ($pCellParent !== NULL) {
								$cellValue = $this->extractCellRange($cellRef, $this->_workbook->getSheetByName($sheet1), FALSE);
							} else {
								return $this->_raiseFormulaError('Unable to access Cell Reference');
							}
							$stack->push('Cell Reference',$cellValue,$cellRef);
						} else {
							$stack->push('Error',PHPExcel_Calculation_Functions::REF(),NULL);
						}

						break;
					case '+'	:			
						$this->_executeNumericBinaryOperation($cellID,$operand1,$operand2,$token,'plusEquals',$stack);
						break;
					case '-'	:			
						$this->_executeNumericBinaryOperation($cellID,$operand1,$operand2,$token,'minusEquals',$stack);
						break;
					case '*'	:			
						$this->_executeNumericBinaryOperation($cellID,$operand1,$operand2,$token,'arrayTimesEquals',$stack);
						break;
					case '/'	:			
						$this->_executeNumericBinaryOperation($cellID,$operand1,$operand2,$token,'arrayRightDivide',$stack);
						break;
					case '^'	:			
						$this->_executeNumericBinaryOperation($cellID,$operand1,$operand2,$token,'power',$stack);
						break;
					case '&'	:			
						if (is_bool($operand1)) {
							$operand1 = ($operand1) ? self::$_localeBoolean['TRUE'] : self::$_localeBoolean['FALSE'];
						}
						if (is_bool($operand2)) {
							$operand2 = ($operand2) ? self::$_localeBoolean['TRUE'] : self::$_localeBoolean['FALSE'];
						}
						if ((is_array($operand1)) || (is_array($operand2))) {
							self::_checkMatrixOperands($operand1,$operand2,2);
							try {
								$matrix = new PHPExcel_Shared_JAMA_Matrix($operand1);
								$matrixResult = $matrix->concat($operand2);
								$result = $matrixResult->getArray();
							} catch (PHPExcel_Exception $ex) {
								$this->_debugLog->writeDebugLog('JAMA Matrix Exception: ', $ex->getMessage());
								$result = '#VALUE!';
							}
						} else {
							$result = '"'.str_replace('""','"',self::_unwrapResult($operand1,'"').self::_unwrapResult($operand2,'"')).'"';
						}
						$this->_debugLog->writeDebugLog('Evaluation Result is ', $this->_showTypeDetails($result));
						$stack->push('Value',$result);
						break;
					case '|'	:			
						$rowIntersect = array_intersect_key($operand1,$operand2);
						$cellIntersect = $oCol = $oRow = array();
						foreach(array_keys($rowIntersect) as $row) {
							$oRow[] = $row;
							foreach($rowIntersect[$row] as $col => $data) {
								$oCol[] = PHPExcel_Cell::columnIndexFromString($col) - 1;
								$cellIntersect[$row] = array_intersect_key($operand1[$row],$operand2[$row]);
							}
						}
						$cellRef = PHPExcel_Cell::stringFromColumnIndex(min($oCol)).min($oRow).':'.PHPExcel_Cell::stringFromColumnIndex(max($oCol)).max($oRow);
						$this->_debugLog->writeDebugLog('Evaluation Result is ', $this->_showTypeDetails($cellIntersect));
						$stack->push('Value',$cellIntersect,$cellRef);
						break;
				}

			} elseif (($token === '~') || ($token === '%')) {
				if (($arg = $stack->pop()) === NULL) return $this->_raiseFormulaError('Internal error - Operand value missing from stack');
				$arg = $arg['value'];
				if ($token === '~') {
					$this->_debugLog->writeDebugLog('Evaluating Negation of ', $this->_showValue($arg));
					$multiplier = -1;
				} else {
					$this->_debugLog->writeDebugLog('Evaluating Percentile of ', $this->_showValue($arg));
					$multiplier = 0.01;
				}
				if (is_array($arg)) {
					self::_checkMatrixOperands($arg,$multiplier,2);
					try {
						$matrix1 = new PHPExcel_Shared_JAMA_Matrix($arg);
						$matrixResult = $matrix1->arrayTimesEquals($multiplier);
						$result = $matrixResult->getArray();
					} catch (PHPExcel_Exception $ex) {
						$this->_debugLog->writeDebugLog('JAMA Matrix Exception: ', $ex->getMessage());
						$result = '#VALUE!';
					}
					$this->_debugLog->writeDebugLog('Evaluation Result is ', $this->_showTypeDetails($result));
					$stack->push('Value',$result);
				} else {
					$this->_executeNumericBinaryOperation($cellID,$multiplier,$arg,'*','arrayTimesEquals',$stack);
				}

			} elseif (preg_match('/^'.self::CALCULATION_REGEXP_CELLREF.'$/i', $token, $matches)) {
				$cellRef = NULL;
				if (isset($matches[8])) {
					if ($pCell === NULL) {
						$cellValue = PHPExcel_Calculation_Functions::REF();
					} else {
						$cellRef = $matches[6].$matches[7].':'.$matches[9].$matches[10];
						if ($matches[2] > '') {
							$matches[2] = trim($matches[2],"\"'");
							if ((strpos($matches[2],'[') !== FALSE) || (strpos($matches[2],']') !== FALSE)) {
								return $this->_raiseFormulaError('Unable to access External Workbook');
							}
							$matches[2] = trim($matches[2],"\"'");
							$this->_debugLog->writeDebugLog('Evaluating Cell Range ', $cellRef, ' in worksheet ', $matches[2]);
							if ($pCellParent !== NULL) {
								$cellValue = $this->extractCellRange($cellRef, $this->_workbook->getSheetByName($matches[2]), FALSE);
							} else {
								return $this->_raiseFormulaError('Unable to access Cell Reference');
							}
							$this->_debugLog->writeDebugLog('Evaluation Result for cells ', $cellRef, ' in worksheet ', $matches[2], ' is ', $this->_showTypeDetails($cellValue));
						} else {
							$this->_debugLog->writeDebugLog('Evaluating Cell Range ', $cellRef, ' in current worksheet');
							if ($pCellParent !== NULL) {
								$cellValue = $this->extractCellRange($cellRef, $pCellWorksheet, FALSE);
							} else {
								return $this->_raiseFormulaError('Unable to access Cell Reference');
							}
							$this->_debugLog->writeDebugLog('Evaluation Result for cells ', $cellRef, ' is ', $this->_showTypeDetails($cellValue));
						}
					}
				} else {
					if ($pCell === NULL) {
						$cellValue = PHPExcel_Calculation_Functions::REF();
					} else {
						$cellRef = $matches[6].$matches[7];
						if ($matches[2] > '') {
							$matches[2] = trim($matches[2],"\"'");
							if ((strpos($matches[2],'[') !== FALSE) || (strpos($matches[2],']') !== FALSE)) {
								return $this->_raiseFormulaError('Unable to access External Workbook');
							}
							$this->_debugLog->writeDebugLog('Evaluating Cell ', $cellRef, ' in worksheet ', $matches[2]);
							if ($pCellParent !== NULL) {
								$cellSheet = $this->_workbook->getSheetByName($matches[2]);
								if ($cellSheet && $cellSheet->cellExists($cellRef)) {
									$cellValue = $this->extractCellRange($cellRef, $this->_workbook->getSheetByName($matches[2]), FALSE);
									$pCell->attach($pCellParent);
								} else {
									$cellValue = NULL;
								}
							} else {
								return $this->_raiseFormulaError('Unable to access Cell Reference');
							}
							$this->_debugLog->writeDebugLog('Evaluation Result for cell ', $cellRef, ' in worksheet ', $matches[2], ' is ', $this->_showTypeDetails($cellValue));
						} else {
							$this->_debugLog->writeDebugLog('Evaluating Cell ', $cellRef, ' in current worksheet');
							if ($pCellParent->isDataSet($cellRef)) {
								$cellValue = $this->extractCellRange($cellRef, $pCellWorksheet, FALSE);
								$pCell->attach($pCellParent);
							} else {
								$cellValue = NULL;
							}
							$this->_debugLog->writeDebugLog('Evaluation Result for cell ', $cellRef, ' is ', $this->_showTypeDetails($cellValue));
						}
					}
				}
				$stack->push('Value',$cellValue,$cellRef);

			} elseif (preg_match('/^'.self::CALCULATION_REGEXP_FUNCTION.'$/i', $token, $matches)) {
				$functionName = $matches[1];
				$argCount = $stack->pop();
				$argCount = $argCount['value'];
				if ($functionName != 'MKMATRIX') {
					$this->_debugLog->writeDebugLog('Evaluating Function ', self::_localeFunc($functionName), '() with ', (($argCount == 0) ? 'no' : $argCount), ' argument', (($argCount == 1) ? '' : 's'));
				}
				if ((isset(self::$_PHPExcelFunctions[$functionName])) || (isset(self::$_controlFunctions[$functionName]))) {	
					if (isset(self::$_PHPExcelFunctions[$functionName])) {
						$functionCall = self::$_PHPExcelFunctions[$functionName]['functionCall'];
						$passByReference = isset(self::$_PHPExcelFunctions[$functionName]['passByReference']);
						$passCellReference = isset(self::$_PHPExcelFunctions[$functionName]['passCellReference']);
					} elseif (isset(self::$_controlFunctions[$functionName])) {
						$functionCall = self::$_controlFunctions[$functionName]['functionCall'];
						$passByReference = isset(self::$_controlFunctions[$functionName]['passByReference']);
						$passCellReference = isset(self::$_controlFunctions[$functionName]['passCellReference']);
					}
					$args = $argArrayVals = array();
					for ($i = 0; $i < $argCount; ++$i) {
						$arg = $stack->pop();
						$a = $argCount - $i - 1;
						if (($passByReference) &&
							(isset(self::$_PHPExcelFunctions[$functionName]['passByReference'][$a])) &&
							(self::$_PHPExcelFunctions[$functionName]['passByReference'][$a])) {
							if ($arg['reference'] === NULL) {
								$args[] = $cellID;
								if ($functionName != 'MKMATRIX') { $argArrayVals[] = $this->_showValue($cellID); }
							} else {
								$args[] = $arg['reference'];
								if ($functionName != 'MKMATRIX') { $argArrayVals[] = $this->_showValue($arg['reference']); }
							}
						} else {
							$args[] = self::_unwrapResult($arg['value']);
							if ($functionName != 'MKMATRIX') { $argArrayVals[] = $this->_showValue($arg['value']); }
						}
					}
					krsort($args);
					if (($passByReference) && ($argCount == 0)) {
						$args[] = $cellID;
						$argArrayVals[] = $this->_showValue($cellID);
					}
					if ($functionName != 'MKMATRIX') {
						if ($this->_debugLog->getWriteDebugLog()) {
							krsort($argArrayVals);
							$this->_debugLog->writeDebugLog('Evaluating ', self::_localeFunc($functionName), '( ', implode(self::$_localeArgumentSeparator.' ',PHPExcel_Calculation_Functions::flattenArray($argArrayVals)), ' )');
						}
					}
						if ($passCellReference) {
							$args[] = $pCell;
						}
						if (strpos($functionCall,'::') !== FALSE) {
							$result = call_user_func_array(explode('::',$functionCall),$args);
						} else {
							foreach($args as &$arg) {
								$arg = PHPExcel_Calculation_Functions::flattenSingleValue($arg);
							}
							unset($arg);
							$result = call_user_func_array($functionCall,$args);
						}
					if ($functionName != 'MKMATRIX') {
						$this->_debugLog->writeDebugLog('Evaluation Result for ', self::_localeFunc($functionName), '() function call is ', $this->_showTypeDetails($result));
					}
					$stack->push('Value',self::_wrapResult($result));
				}

			} else {
				if (isset(self::$_ExcelConstants[strtoupper($token)])) {
					$excelConstant = strtoupper($token);
					$stack->push('Constant Value',self::$_ExcelConstants[$excelConstant]);
					$this->_debugLog->writeDebugLog('Evaluating Constant ', $excelConstant, ' as ', $this->_showTypeDetails(self::$_ExcelConstants[$excelConstant]));
				} elseif ((is_numeric($token)) || ($token === NULL) || (is_bool($token)) || ($token == '') || ($token[0] == '"') || ($token[0] == '#')) {
					$stack->push('Value',$token);
				} elseif (preg_match('/^'.self::CALCULATION_REGEXP_NAMEDRANGE.'$/i', $token, $matches)) {
					$namedRange = $matches[6];
					$this->_debugLog->writeDebugLog('Evaluating Named Range ', $namedRange);
					$cellValue = $this->extractNamedRange($namedRange, ((NULL !== $pCell) ? $pCellWorksheet : NULL), FALSE);
					$pCell->attach($pCellParent);
					$this->_debugLog->writeDebugLog('Evaluation Result for named range ', $namedRange, ' is ', $this->_showTypeDetails($cellValue));
					$stack->push('Named Range',$cellValue,$namedRange);
				} else {
					return $this->_raiseFormulaError("undefined variable '$token'");
				}
			}
		}
		if ($stack->count() != 1) return $this->_raiseFormulaError("internal error");
		$output = $stack->pop();
		$output = $output['value'];

		return $output;
	}	


	private function _validateBinaryOperand($cellID, &$operand, &$stack) {
		if (is_array($operand)) {
			if ((count($operand, COUNT_RECURSIVE) - count($operand)) == 1) {
				do {
					$operand = array_pop($operand);
				} while (is_array($operand));
			}
		}
		if (is_string($operand)) {
			if ($operand > '' && $operand[0] == '"') { $operand = self::_unwrapResult($operand); }
			if (!is_numeric($operand)) {
				if ($operand > '' && $operand[0] == '#') {
					$stack->push('Value', $operand);
					$this->_debugLog->writeDebugLog('Evaluation Result is ', $this->_showTypeDetails($operand));
					return FALSE;
				} elseif (!PHPExcel_Shared_String::convertToNumberIfFraction($operand)) {
					$stack->push('Value', '#VALUE!');
					$this->_debugLog->writeDebugLog('Evaluation Result is a ', $this->_showTypeDetails('#VALUE!'));
					return FALSE;
				}
			}
		}

		return TRUE;
	}	


	private function _executeBinaryComparisonOperation($cellID, $operand1, $operand2, $operation, &$stack, $recursingArrays=FALSE) {
		if ((is_array($operand1)) || (is_array($operand2))) {
			$result = array();
			if ((is_array($operand1)) && (!is_array($operand2))) {
				foreach($operand1 as $x => $operandData) {
					$this->_debugLog->writeDebugLog('Evaluating Comparison ', $this->_showValue($operandData), ' ', $operation, ' ', $this->_showValue($operand2));
					$this->_executeBinaryComparisonOperation($cellID,$operandData,$operand2,$operation,$stack);
					$r = $stack->pop();
					$result[$x] = $r['value'];
				}
			} elseif ((!is_array($operand1)) && (is_array($operand2))) {
				foreach($operand2 as $x => $operandData) {
					$this->_debugLog->writeDebugLog('Evaluating Comparison ', $this->_showValue($operand1), ' ', $operation, ' ', $this->_showValue($operandData));
					$this->_executeBinaryComparisonOperation($cellID,$operand1,$operandData,$operation,$stack);
					$r = $stack->pop();
					$result[$x] = $r['value'];
				}
			} else {
				if (!$recursingArrays) { self::_checkMatrixOperands($operand1,$operand2,2); }
				foreach($operand1 as $x => $operandData) {
					$this->_debugLog->writeDebugLog('Evaluating Comparison ', $this->_showValue($operandData), ' ', $operation, ' ', $this->_showValue($operand2[$x]));
					$this->_executeBinaryComparisonOperation($cellID,$operandData,$operand2[$x],$operation,$stack,TRUE);
					$r = $stack->pop();
					$result[$x] = $r['value'];
				}
			}
			$this->_debugLog->writeDebugLog('Comparison Evaluation Result is ', $this->_showTypeDetails($result));
			$stack->push('Array',$result);
			return TRUE;
		}

		if (is_string($operand1) && $operand1 > '' && $operand1[0] == '"') { $operand1 = self::_unwrapResult($operand1); }
		if (is_string($operand2) && $operand2 > '' && $operand2[0] == '"') { $operand2 = self::_unwrapResult($operand2); }

		if (PHPExcel_Calculation_Functions::getCompatibilityMode() != PHPExcel_Calculation_Functions::COMPATIBILITY_OPENOFFICE)
		{
			if (is_string($operand1)) {
				$operand1 = strtoupper($operand1);
			}

			if (is_string($operand2)) {
				$operand2 = strtoupper($operand2);
			}
		}

		$useLowercaseFirstComparison = is_string($operand1) && is_string($operand2) && PHPExcel_Calculation_Functions::getCompatibilityMode() == PHPExcel_Calculation_Functions::COMPATIBILITY_OPENOFFICE;

		switch ($operation) {
			case '>':
				if ($useLowercaseFirstComparison) {
					$result = $this->strcmpLowercaseFirst($operand1, $operand2) > 0;
				} else {
					$result = ($operand1 > $operand2);
				}
				break;
			case '<':
				if ($useLowercaseFirstComparison) {
					$result = $this->strcmpLowercaseFirst($operand1, $operand2) < 0;
				} else {
					$result = ($operand1 < $operand2);
				}
				break;
			case '=':
				$result = ($operand1 == $operand2);
				break;
			case '>=':
				if ($useLowercaseFirstComparison) {
					$result = $this->strcmpLowercaseFirst($operand1, $operand2) >= 0;
				} else {
					$result = ($operand1 >= $operand2);
				}
				break;
			case '<=':
				if ($useLowercaseFirstComparison) {
					$result = $this->strcmpLowercaseFirst($operand1, $operand2) <= 0;
				} else {
					$result = ($operand1 <= $operand2);
				}
				break;
			case '<>':
				$result = ($operand1 != $operand2);
				break;
		}

		$this->_debugLog->writeDebugLog('Evaluation Result is ', $this->_showTypeDetails($result));
		$stack->push('Value',$result);
		return TRUE;
	}	

	private function strcmpLowercaseFirst($str1, $str2)
	{
		$from = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$to = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$inversedStr1 = strtr($str1, $from, $to);
		$inversedStr2 = strtr($str2, $from, $to);

		return strcmp($inversedStr1, $inversedStr2);
	}

	private function _executeNumericBinaryOperation($cellID,$operand1,$operand2,$operation,$matrixFunction,&$stack) {
		if (!$this->_validateBinaryOperand($cellID,$operand1,$stack)) return FALSE;
		if (!$this->_validateBinaryOperand($cellID,$operand2,$stack)) return FALSE;

		if ((is_array($operand1)) || (is_array($operand2))) {
			self::_checkMatrixOperands($operand1, $operand2, 2);

			try {
				$matrix = new PHPExcel_Shared_JAMA_Matrix($operand1);
				$matrixResult = $matrix->$matrixFunction($operand2);
				$result = $matrixResult->getArray();
			} catch (PHPExcel_Exception $ex) {
				$this->_debugLog->writeDebugLog('JAMA Matrix Exception: ', $ex->getMessage());
				$result = '#VALUE!';
			}
		} else {
			if ((PHPExcel_Calculation_Functions::getCompatibilityMode() != PHPExcel_Calculation_Functions::COMPATIBILITY_OPENOFFICE) &&
				((is_string($operand1) && !is_numeric($operand1) && strlen($operand1)>0) ||
                 (is_string($operand2) && !is_numeric($operand2) && strlen($operand2)>0))) {
				$result = PHPExcel_Calculation_Functions::VALUE();
			} else {
				switch ($operation) {
					case '+':
						$result = $operand1 + $operand2;
						break;
					case '-':
						$result = $operand1 - $operand2;
						break;
					case '*':
						$result = $operand1 * $operand2;
						break;
					case '/':
						if ($operand2 == 0) {
							$stack->push('Value','#DIV/0!');
							$this->_debugLog->writeDebugLog('Evaluation Result is ', $this->_showTypeDetails('#DIV/0!'));
							return FALSE;
						} else {
							$result = $operand1 / $operand2;
						}
						break;
					case '^':
						$result = pow($operand1, $operand2);
						break;
				}
			}
		}

		$this->_debugLog->writeDebugLog('Evaluation Result is ', $this->_showTypeDetails($result));
		$stack->push('Value',$result);
		return TRUE;
	}	


	protected function _raiseFormulaError($errorMessage) {
		$this->formulaError = $errorMessage;
		$this->_cyclicReferenceStack->clear();
		if (!$this->suppressFormulaErrors) throw new PHPExcel_Calculation_Exception($errorMessage);
		trigger_error($errorMessage, E_USER_ERROR);
	}	


	public function extractCellRange(&$pRange = 'A1', PHPExcel_Worksheet $pSheet = NULL, $resetLog = TRUE) {
		$returnValue = array ();

		if ($pSheet !== NULL) {
			$pSheetName = $pSheet->getTitle();
			if (strpos ($pRange, '!') !== false) {
				list($pSheetName,$pRange) = PHPExcel_Worksheet::extractSheetTitle($pRange, true);
				$pSheet = $this->_workbook->getSheetByName($pSheetName);
			}

			$aReferences = PHPExcel_Cell::extractAllCellReferencesInRange($pRange);
			$pRange = $pSheetName.'!'.$pRange;
			if (!isset($aReferences[1])) {
				sscanf($aReferences[0],'%[A-Z]%d', $currentCol, $currentRow);
				$cellValue = NULL;
				if ($pSheet->cellExists($aReferences[0])) {
					$returnValue[$currentRow][$currentCol] = $pSheet->getCell($aReferences[0])->getCalculatedValue($resetLog);
				} else {
					$returnValue[$currentRow][$currentCol] = NULL;
				}
			} else {
				foreach ($aReferences as $reference) {
					sscanf($reference,'%[A-Z]%d', $currentCol, $currentRow);
					$cellValue = NULL;
					if ($pSheet->cellExists($reference)) {
						$returnValue[$currentRow][$currentCol] = $pSheet->getCell($reference)->getCalculatedValue($resetLog);
					} else {
						$returnValue[$currentRow][$currentCol] = NULL;
					}
				}
			}
		}

		return $returnValue;
	}	


	public function extractNamedRange(&$pRange = 'A1', PHPExcel_Worksheet $pSheet = NULL, $resetLog = TRUE) {
		$returnValue = array ();

		if ($pSheet !== NULL) {
			$pSheetName = $pSheet->getTitle();
			if (strpos ($pRange, '!') !== false) {
				list($pSheetName,$pRange) = PHPExcel_Worksheet::extractSheetTitle($pRange, true);
				$pSheet = $this->_workbook->getSheetByName($pSheetName);
			}

			$namedRange = PHPExcel_NamedRange::resolveRange($pRange, $pSheet);
			if ($namedRange !== NULL) {
				$pSheet = $namedRange->getWorksheet();
				$pRange = $namedRange->getRange();
				$splitRange = PHPExcel_Cell::splitRange($pRange);
				if (ctype_alpha($splitRange[0][0])) {
					$pRange = $splitRange[0][0] . '1:' . $splitRange[0][1] . $namedRange->getWorksheet()->getHighestRow();
				} elseif(ctype_digit($splitRange[0][0])) {
					$pRange = 'A' . $splitRange[0][0] . ':' . $namedRange->getWorksheet()->getHighestColumn() . $splitRange[0][1];
				}

			} else {
				return PHPExcel_Calculation_Functions::REF();
			}

			$aReferences = PHPExcel_Cell::extractAllCellReferencesInRange($pRange);
			if (!isset($aReferences[1])) {
				list($currentCol,$currentRow) = PHPExcel_Cell::coordinateFromString($aReferences[0]);
				$cellValue = NULL;
				if ($pSheet->cellExists($aReferences[0])) {
					$returnValue[$currentRow][$currentCol] = $pSheet->getCell($aReferences[0])->getCalculatedValue($resetLog);
				} else {
					$returnValue[$currentRow][$currentCol] = NULL;
				}
			} else {
				foreach ($aReferences as $reference) {
					list($currentCol,$currentRow) = PHPExcel_Cell::coordinateFromString($reference);
					$cellValue = NULL;
					if ($pSheet->cellExists($reference)) {
						$returnValue[$currentRow][$currentCol] = $pSheet->getCell($reference)->getCalculatedValue($resetLog);
					} else {
						$returnValue[$currentRow][$currentCol] = NULL;
					}
				}
			}
		}

		return $returnValue;
	}	


	public function isImplemented($pFunction = '') {
		$pFunction = strtoupper ($pFunction);
		if (isset(self::$_PHPExcelFunctions[$pFunction])) {
			return (self::$_PHPExcelFunctions[$pFunction]['functionCall'] != 'PHPExcel_Calculation_Functions::DUMMY');
		} else {
			return FALSE;
		}
	}	


	public function listFunctions() {
		$returnValue = array();
		foreach(self::$_PHPExcelFunctions as $functionName => $function) {
			if ($function['functionCall'] != 'PHPExcel_Calculation_Functions::DUMMY') {
				$returnValue[$functionName] = new PHPExcel_Calculation_Function($function['category'],
																				$functionName,
																				$function['functionCall']
																			   );
			}
		}

		return $returnValue;
	}	


	public function listAllFunctionNames() {
		return array_keys(self::$_PHPExcelFunctions);
	}	

	public function listFunctionNames() {
		$returnValue = array();
		foreach(self::$_PHPExcelFunctions as $functionName => $function) {
			if ($function['functionCall'] != 'PHPExcel_Calculation_Functions::DUMMY') {
				$returnValue[] = $functionName;
			}
		}

		return $returnValue;
	}	

}	

