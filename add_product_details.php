<?php
require 'vendor/autoload.php';
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$product_extractor = new ProductExtractor();
try {
    //for ($i = 0; $i < 160; $i++) {
        $product_extractor->next();
        //sleep(1);
    //}
} catch (Exception $e) {
    echo $e->getMessage()."\n";
}
exit;

class ProductExtractor
{

    private \MongoDB\Client $mongo;
    private \MongoDB\Collection $collection;
    private $youla_url = 'https://youla.ru';

    private Logger $log;

    function __construct()
    {
        $this->log = new Logger('parser');
        $this->log->pushHandler(new StreamHandler(getenv('CRON_LOG') ?: 'parser.log', Level::Warning));

        $this->mongo = $this->get_connection();
        $this->collection = $this->mongo->youla->parsed;
    }

    function get_connection () {
        $MONGO_HOST = getenv('MONGO_HOST') ?: 'not_defined';
        $MONGO_USER = getenv('MONGO_USER') ?: '';
        $MONGO_PASS = getenv('MONGO_PASS') ?: '';
        $MONGO_PORT = getenv('MONGO_PORT') ?: 27017;
        if ( $MONGO_HOST == 'not_defined' ) {
            $this->error('MONGO_HOST not defined');
            exit;
        } elseif ( $MONGO_HOST == '192.168.1.37') {
            $uri = "mongodb://$MONGO_HOST:$MONGO_PORT";
        } else {
            $uri = "mongodb://$MONGO_USER:$MONGO_PASS@$MONGO_HOST:$MONGO_PORT";
        }
        return new MongoDB\Client($uri);
    }

    function warning( $message ) {
        echo $message."\n";
        $this->log->warning($message);
    }

    function error( $message ) {
        echo $message."\n";
        $this->log->error($message);
    }

    function next() {
        $empty_item = $this->get_empty_item();
        try {
            $product_details = $this->parse_item($empty_item);
            $this->update_item($empty_item, $product_details);
        } catch ( Exception $e ) {
            $this->error('Exception '.$e->getMessage());
            $this->delete_item($empty_item);

        }
    }

    function update_item ( $item, $product_details ) {
        $update_result = $this->collection->updateOne(
            ['_id' => $item['_id']],
            ['$set' => ['product_details' => $product_details]]
        );
        $this->warning("Record id = {$item['_id']},".
        "{$product_details['products'][0]['name']},".
        date('Y-m-d H:i:s',$product_details['products'][0]['datePublished']['timestamp']).", updated: " . $update_result->getModifiedCount());
    }

    function delete_item ( $item ) {
        $result = $this->collection->deleteOne(
            ['_id' => $item['_id']]
        );
        $this->warning("Delete record id = {$item['_id']} ");
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
        $response_code = $this->get_http_response_code($url);
        if ( $response_code != "200" and $response_code != "429" ){
            throw new Exception('http response = '.$response_code);
        }

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

    function get_http_response_code($url) {
        $headers = get_headers($url);
        return substr($headers[0], 9, 3);
    }
}
