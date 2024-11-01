<?php

namespace UkrSolution\UpcEanGenerator;

class Hooks
{
    static public function jsonResponse($data)
    {
        @header('Content-type: application/json; charset=utf-8');
        echo json_encode($data);
        wp_die();
    }
}
