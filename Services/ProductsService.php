<?php

namespace Services;

use Helpers\DimensionsHelper;

class ProductsService
{
    /**
     * Function to obtain the insurance value of one or more products.
     *
     * @param array|object $products
     * @return float
     */
    public function getInsuranceValue($products)
    {
        $insuranceValue = 0;
        foreach ($products as $product) {
            $value = 0;
            if (!empty($product->unitary_value)) {
                $value = $product->unitary_value * $product->quantity;
            }
            $insuranceValue = $insuranceValue + $value;
        }

        if ($insuranceValue === 0) {
            $insuranceValue = 1;
        }

        return $insuranceValue;
    }

    /**
     * function to remove the price field from
     * the product to perform the quote without insurance value
     *
     * @param array $products
     * @return array
     */
    public function removePrice($products)
    {
        $response = [];
        foreach ($products as $product) {
            $response[] = (object) [
                'id' => $product->id,
                'name' => $product->name,
                'quantity' => $product->quantity,
                'unitary_value' => $product->unitary_value,
                'weight' => $product->weight,
                'width' => $product->width,
                'height' => $product->height,
                'length' => $product->length,
            ];
        }

        return $response;
    }

    /**
     * Function to filter products to api Melhor Envio.
     *
     * @param array $products
     * @return array
     */
    public function filter($data)
    {
        $products = [];
        $price = 0;

        $noticeService = new SessionNoticeService();

        $length = 1;
        $total = count($data);

        if($total > 6) {
            $length = ceil($total/6);
        }

        foreach ($data as $item) {
            if (!empty($item['data'])) {
                $product = $item['data'];
                $price += $product->get_price();
            }        
        }

        $data = array_slice($data, 0, $length);    

        foreach ($data as $item) {
            if (empty($item['data'])) {
                $products[] = (object) $item;
            } else {
                $product = $item['data'];

                if (!$this->hasAllDimensions(($product))) {
                    $message = sprintf(
                        "Verificar as medidas do produto  <a href='%s'>%s</a>",
                        get_edit_post_link($product->get_id()),
                        $product->get_name()
                    );
                    $noticeService->add($message);
                }

                $products[] = (object) [
                    'id' =>  1,
                    'name' =>  "Encomenda useUp!",
                    'width' =>  DimensionsHelper::convertUnitDimensionToCentimeter(11),
                    'height' => DimensionsHelper::convertUnitDimensionToCentimeter(4),
                    'length' => DimensionsHelper::convertUnitDimensionToCentimeter(17),
                    'weight' =>  DimensionsHelper::convertWeightUnit(0.1),
                    'unitary_value' => $price,
                    'insurance_value' => $price,
                    'quantity' =>   1
                ];

            }
        }

        return $products;
    }

    /**
     * function to check if prouct has all dimensions.
     *
     * @param object $product
     * @return boolean
     */
    private function hasAllDimensions($product)
    {
        return (!empty($product->get_width()) &&
            !empty($product->get_height()) &&
            !empty($product->get_length()) &&
            !empty($product->get_weight()));
    }

    /**
     * function to return a label with the name of products.
     *
     * @param array $products
     * @return string
     */
    public function createLabelTitleProducts($products)
    {
        $title = '';
        foreach ($products as $id => $product) {
            if (!empty($product['data']->get_name())) {
                $title = $title . sprintf(
                    "<a href='%s'>%s</a>, ",
                    get_edit_post_link($id),
                    $product['data']->get_name()
                );
            }
        }

        if (!empty($title)) {
            $title = substr($title, 0, -2);
        }

        return 'Produto(s): ' . $title;
    }
}
