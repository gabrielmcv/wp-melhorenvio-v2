<?php

namespace Controllers;

use Models\Order;
use Controllers\UsersController;
use Controllers\PackageController;
use Controllers\ProductsController;

class OrdersController {

    public function index() {
        $orders = Order::retrieveMany();
    }

    public function getOrders() {
        unset($_GET['action']);
        $orders = Order::getAllOrders($_GET);
        return json_encode($orders);
    }

    public function sendOrder() {

        $token = get_option('melhorenvio_token');
        $user = new UsersController();

        $package = new PackageController();
        $products = new ProductsController();

        $body = [
            'from' => $user->getFrom(),
            'to' => $user->getTo($_GET['order_id']),
            'service' => $_GET['choosen'],
            'agency' => null,
            'products' => $products->getProductsOrder($_GET['order_id']),
            'package' => $package->getPackageOrder($_GET['order_id']),
            'options' => [
                "insurance_value" => $products->getInsuranceValue($_GET['order_id']), 
                "receipt" => false,
                "own_hand" => false,
                "collect" => false,
                "reverse" => false, 
                "non_commercial" => false, 
                "invoice" => [
                    "number" => null, 
                    "key" => null 
                ]
            ]
        ];

        $params = array(
            'headers'           =>  [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
            'body'  => json_encode($body),
            'timeout'=>10
        );

        $response =  json_decode(wp_remote_retrieve_body(wp_remote_post('https://www.melhorenvio.com.br/api/v2/me/cart', $params)));

        // TODO verificar os error de retorno
        if ($response->error) {
            echo json_encode([
                'error' => true,
                'message' => $response->error
            ]);
            die;
        }

        $this->updateDataCotation($_GET['order_id'], $response, 'cart');

        echo json_encode([
            'success' => true,
            'data' => $response
        ]);
        die;
    }

    private function updateDataCotation($order_id, $data, $status) {
        $cotation = get_post_meta($order_id, 'melhorenvio_cotation_v2', true);

        $cotation['choose_method'] = $data->service_id;
        $cotation['order_id'] = $data->id;
        $cotation['protocol'] = $data->protocol;
        $cotation['status'] = 'cart';

        $cotation = [
            'choose_method' => $data->service_id,
            'order_id' => $data->id,
            'protocol' => $data->protocol,
            'status' => $status
        ];

        add_post_meta($order_id, 'melhorenvio_cotation_v2', $cotation);
    }
}
