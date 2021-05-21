<?php

namespace Helpers;

use Services\OptionsMethodShippingService;

class TimeHelper
{
    /**
     * Function to define custom delivery time
     * 
     * @param array $data
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

        $response = wp_remote_get("https://www.dias-uteis.com/analyse_intervalle.php?fromjs=1&add_jo=".$max);
         
        if ( is_array( $response ) && ! is_wp_error( $response ) ) {
            $body = $response['body'];
            $body = explode("}", $body);
            $date = strtotime(str_replace('/', '-', $body[0]));
            $daysLabel = array('Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado');
            $week_day = date("w", $date);
            $max_date = date("d/m", $date);
            $week_day_label = $daysLabel[$week_day];

            if ($max == 1 && date('l') != "Friday") {
                return " (Chega até amanhã)";
            }

            if ($max < 7 && date('w') < $week_day) {
                return sprintf(" (Chega até %s)", $week_day_label);
            }

            return sprintf(" (Chega até %s)", $max_date);


        }        

        return sprintf(" (%s dias úteis)", $max);
    }
}
