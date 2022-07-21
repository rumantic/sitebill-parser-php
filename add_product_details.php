<?php
require 'vendor/autoload.php';
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$product_extractor = new ProductExtractor();
try {
    //for ($i = 0; $i < 160; $i++) {
        $product_extractor->next();
        //sleep(10);
    //}
} catch (Exception $e) {
    echo $e->getMessage()."\n";
}
exit;

class ProductExtractor
{

    private \MongoDB\Client $mongo;
    private \MongoDB\Collection $collection;
    private $youla_url = 'https://youla.io';

    private Logger $log;

    function __construct()
    {
        $this->mongo = new MongoDB\Client("mongodb://localhost:27017");
        $this->collection = $this->mongo->youla->parsed;
        $this->log = new Logger('parser');
        $this->log->pushHandler(new StreamHandler('parser.log', Level::Warning));
    }

    function warning( $message ) {
        echo $message."\n";
        $this->log->warning($message);
    }

    function next() {
        $empty_item = $this->get_empty_item();
        $product_details = $this->parse_item($empty_item);
        $this->update_item($empty_item, $product_details);
    }

    function update_item ( $item, $product_details ) {
        $update_result = $this->collection->updateOne(
            ['_id' => $item['_id']],
            ['$set' => ['product_details' => $product_details]]
        );
        $this->warning("Record id = {$item['_id']}, {$product_details['products'][0]['name']}, updated: " . $update_result->getModifiedCount());
    }

    function parse_item ($item) {
        $url = $this->youla_url.$item['product']['url'];
        $this->warning('Start parsing '.$url);
        $product_details = $this->parse_page($url);
        return $product_details;
    }

    function get_empty_item()
    {
        $result = $this->collection->findOne(['product_details' => null]);
        if ( $result ) {
            return $result;
        }
        throw new Exception('Cant find records with null details');
    }

    function parse_page( $url )
    {
        $content = file_get_contents($url);
        $pattern = '/window\.__YOULA_STATE__ = (.*);/';
        preg_match($pattern, $content, $matches);
        $youla_state = $matches[1];
        if ($youla_state) {
            $youla_state_array = json_decode($youla_state, true);
        }
        if ( isset($youla_state_array['entities']) ) {
            return $youla_state_array['entities'];
        }
        throw new Exception('Cant parse url: '.$url);
    }
}
