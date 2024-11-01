<?php

namespace UkrSolution\UpcEanGenerator\features\codes;

use UkrSolution\UpcEanGenerator\features\products\Products;
use UkrSolution\UpcEanGenerator\features\settings\Settings;
use UkrSolution\UpcEanGenerator\Hooks;

class Codes
{

    public function regenerateCode()
    {
        $products = new Products();
        $code = $this->generate($products->fieldName);

        Hooks::jsonResponse(array("code" => $code));
    }

    public function generate($fieldName)
    {

        $settings = new Settings();
        if ($settings->getField("general", "code-type", "upc") === "upc") {
            return $this->generateUPC();
        } else {
            return $this->generateEAN();
        }
    }

    private function generateEAN()
    {
        $number = mt_rand(1000000000, 9999999999);

        $EAN = '20' . str_pad($number, 10, '0');
        $weightflag = true;
        $sum = 0;

        for ($i = strlen($EAN) - 1; $i >= 0; $i--) {
            $sum += (int)$EAN[$i] * ($weightflag ? 3 : 1);
            $weightflag = !$weightflag;
        }

        $EAN .= (10 - ($sum % 10)) % 10;

        return $EAN;

    }

    private function generateUPC()
    {
        $prefix = "4" . mt_rand(10000, 99999);
        $product = mt_rand(10000, 99999);
        $code = str_split(($prefix . $product), 1);
        $UPC = "";

        $sum1 = 0;
        $sum2 = 0;

        foreach ($code as $key => $value) {
            if ($key === 0 || ($key % 2) === 0) {
                $sum1 += $value;
            } else if (($key % 2) !== 0) {
                $sum2 += $value;
            }
        }

        $sum = ($sum1 * 3) + $sum2;

        if ($sum < 10) {
            $UPC = $prefix . $product . (10 - $sum);
        } else if (($sum % 10) !== 0) {
            $UPC = $prefix . $product . (10 - $sum % 10);
        } else {
            $UPC = $prefix . $product . "0";
        }

        return $UPC;

    }
}
