<?php

namespace MelhorEnvio\Helpers;

use MelhorEnvio\Services\OptionsMethodShippingService;

class TimeHelper {

    /**
     * Function to define custom delivery time
     *
     * @param array  $data
     * @param object $extra
     *
     * @return string
     */
    public static function label($data, $extra)
    {

        $min = intval($data->min) + intval($extra);
        $max = intval($data->max) + intval($extra);
        
        //if($max > 15) $max = 15;

        if (empty($data)) {
            return ' (*)';
        }

        $response = self::getNextUtilDate($max);
        $date = strtotime(str_replace('/', '-', $response));
        $daysLabel = array('Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado');
        $week_number = date("W", $date);
        $week_day = date("w", $date);
        $max_date = date("d/m", $date);
        $week_day_label = $daysLabel[$week_day];

        if ($max == 1 && date('l') != "Friday") {
            return " (Chega até amanhã)";
        }

        if ($max < 7 && date('w') < $week_day && date('W') == $week_number) {
            return sprintf(" (Chega até %s)", $week_day_label);
        }

        return sprintf(" (Chega até %s)", $max_date);

    }

    /**
     * @param string $date
     * @return float
     */
    public static function getDiffFromNowInSeconds( $date ) {
        $now   = date( 'Y-m-d H:i:s' );
        $start = strtotime( $now );
        $end   = strtotime( $date );
        return $start - $end;
    }


    // Função para checar se é dia útil
    public static function checkIfUtilDay($data) {

        if(!$data) return false;

        $feriados = array();
        $ano = date('Y', $data);

    // Pega a data da páscoa
        $pascoa = easter_date($ano); 
        $dia_pascoa = date('j', $pascoa);
        $mes_pascoa = date('n', $pascoa);
        $ano_pascoa = date('Y', $pascoa);

    // Feriados com data fixa
        $feriados[] = date('d/m/Y', mktime(0, 0, 0, 1, 1, $ano));
        $feriados[] = date('d/m/Y', mktime(0, 0, 0, 4, 21, $ano));
        $feriados[] = date('d/m/Y', mktime(0, 0, 0, 5, 1, $ano));
        $feriados[] = date('d/m/Y', mktime(0, 0, 0, 9, 7, $ano));
        $feriados[] = date('d/m/Y', mktime(0, 0, 0, 10, 12, $ano));
        $feriados[] = date('d/m/Y', mktime(0, 0, 0, 11, 2, $ano));
        $feriados[] = date('d/m/Y', mktime(0, 0, 0, 11, 15, $ano));
        $feriados[] = date('d/m/Y', mktime(0, 0, 0, 12, 25, $ano));
    // Feriados estaduais e extras
        $feriados[] = date('d/m/Y', mktime(0, 0, 0, 4, 17, $ano));  
        $feriados[] = date('d/m/Y', mktime(0, 0, 0, 4, 21, $ano));  

    //Feriados com data variável
        $feriados[] = date('d/m/Y', mktime(0, 0, 0, $mes_pascoa, $dia_pascoa - 47, $ano_pascoa));
        $feriados[] = date('d/m/Y', mktime(0, 0, 0, $mes_pascoa, $dia_pascoa - 46, $ano_pascoa));
        $feriados[] = date('d/m/Y', mktime(0, 0, 0, $mes_pascoa, $dia_pascoa - 45, $ano_pascoa));       
        $feriados[] = date('d/m/Y', mktime(0, 0, 0, $mes_pascoa, $dia_pascoa - 2, $ano_pascoa));
        $feriados[] = date('d/m/Y', mktime(0, 0, 0, $mes_pascoa, $dia_pascoa, $ano_pascoa));
        $feriados[] = date('d/m/Y', mktime(0, 0, 0, $mes_pascoa, $dia_pascoa + 60, $ano_pascoa));

        if(!in_array(date('d/m/Y', $data), $feriados) && date("N", $data) <= 5) return true;
        return false;
    }

    // Função para encontrar a data com base em dias úteis fornecidos
    public static function getNextUtilDate($diasUteis) {
        $dataAtual = time(); // Data atual em timestamp
        $diasContados = 0;

        while ($diasContados < $diasUteis) {
            $dataAtual += 24 * 60 * 60; // Adiciona um dia (em segundos)
            if (self::checkIfUtilDay($dataAtual)) {
                $diasContados++;
            }
        }

        return date('d/m/Y', $dataAtual);
    }


}
