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
    echo date('Y-m-d H:i:s').' '.$e->getMessage()."\n";
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
        echo date('Y-m-d H:i:s').' '.$message."\n";
        $this->log->warning($message);
    }

    function error( $message ) {
        echo date('Y-m-d H:i:s').' '.$message."\n";
        $this->log->error($message);
    }

    function next() {
        $empty_item = $this->get_empty_item();
        if ( !$empty_item ) {
            return false;
        }
        try {
            $product_details = $this->parse_item($empty_item);
            $this->update_item($empty_item, $product_details);
        } catch ( Exception $e ) {
            $this->error('Exception '.$e->getMessage());
            if ($e->getMessage() == 'not_found') {
                $this->delete_item($empty_item);
            }
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
        return false;
    }

    function parse_page( $url )
    {
        $content = file_get_contents($url);
        $lastError = error_get_last();
        if ( $lastError['message'] and preg_match('/404 Not Found/', $lastError['message']) ) {
            throw new Exception('not_found');
        }

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

    function get_proxy () {
        $proxy = '
163.116.131.129:8080
80.48.119.28:8080
66.29.154.103:3128
169.57.1.85:8123
66.29.154.105:3128
185.61.152.137:8080
8.219.97.248:80
200.89.174.158:8080
139.99.237.62:80
47.245.33.104:12345
49.207.36.81:80
20.110.214.83:80
41.59.90.92:80
23.236.109.50:80
218.238.83.182:80
163.53.208.93:8080
198.59.191.234:8080
121.1.41.162:111
125.21.3.41:8080
66.196.238.179:3128
181.196.253.122:9812
91.230.199.174:61440
20.81.62.32:3128
103.148.72.106:80
104.248.40.73:80
35.234.248.49:3128
191.97.9.189:999
200.106.236.142:3128
110.77.241.248:8080
188.0.147.102:3128
200.105.215.18:33630
222.237.249.172:8080
182.72.150.242:8080
40.67.252.70:8080
51.250.80.131:80
8.208.82.80:8081
71.19.249.118:8001
198.50.205.214:3128
103.142.110.98:8080
181.78.27.38:999
115.124.85.19:8080
95.0.7.16:8080
203.150.128.110:8080
120.28.218.28:3128
202.152.54.135:808
172.105.40.29:3128
45.177.109.197:8080
192.46.210.14:3128
178.54.21.203:8081
198.199.86.11:3128
75.72.76.3:8118
133.18.195.135:8080
165.154.226.242:80
8.214.4.72:33080
197.243.14.59:8888
208.109.11.232:8089
154.16.63.16:8080
103.149.162.195:80
45.70.201.177:999
72.169.65.57:87
78.154.180.52:81
41.222.209.12:808
118.36.19.164:8080
41.39.244.130:32604
36.95.147.251:8080
117.54.11.85:3128
47.91.44.217:8000
41.65.236.43:1976
195.158.3.198:3128
202.40.177.69:80
37.120.192.154:8080
47.74.152.29:8888
14.241.111.38:8080
103.166.10.9:8181
103.48.71.123:83
190.109.16.145:999
103.156.216.178:443
92.247.2.26:21231
212.46.230.102:6969
85.195.104.71:80
173.82.100.189:59394
201.91.18.85:8000
159.65.69.186:9300
181.224.207.19:999
195.211.219.146:5555
102.69.32.1:8080
68.64.250.38:8080
193.3.40.250:8080
103.154.229.250:8080
103.78.255.39:8080
101.255.164.58:8080
103.103.88.162:8080
139.255.109.27:8080
36.91.108.142:3128
200.114.96.18:999
103.145.253.237:3128
180.210.14.4:8000
144.22.173.214:3128
163.172.85.150:9741
41.193.84.196:3128
201.77.108.96:999
103.111.53.102:63238
186.1.206.154:3128
200.24.159.191:999
103.154.92.74:8080
185.82.99.211:9091
5.167.141.239:3128
187.216.93.20:55443
203.150.113.59:8080
176.192.70.58:8022
104.248.203.46:3129
47.91.149.178:8443
103.152.100.155:8080
177.234.217.92:999
46.246.4.16:8118
128.199.202.122:8080
37.144.180.52:8000
47.241.245.186:80
170.83.242.250:999
67.206.202.145:999
187.17.232.6:8089
194.169.167.5:8080
58.147.186.228:3125
61.9.34.46:1337
64.189.24.250:3129
157.100.12.138:999
104.248.38.3:80
201.222.45.65:999
181.224.207.18:999
80.84.176.110:8080
45.224.119.10:999
200.24.157.115:999
140.227.59.167:3180
187.251.138.81:3128
200.25.254.193:54240
103.145.76.44:80
103.60.161.2:80
103.117.231.42:80
103.127.1.130:80
103.115.26.254:80
103.144.48.114:80
173.212.216.104:3128
103.197.251.202:80
154.201.39.251:3128
116.203.201.82:8443
142.252.223.114:3128
172.252.1.189:3128
172.121.142.88:3128
154.201.40.159:3128
45.145.131.134:8085
193.233.142.150:8085
93.120.32.226:9410
45.67.212.250:8085
95.164.234.91:5796
45.9.122.122:8203
181.129.74.58:40667
85.209.151.198:8085
142.252.26.249:3128
172.252.224.129:3128
93.177.116.245:8085
142.252.198.253:3128
85.208.87.149:8085
104.227.6.21:3128
95.181.151.206:8085
193.56.72.30:8085
95.164.235.202:6258
172.252.231.149:3128
109.94.220.149:8085
177.153.33.94:80
166.88.122.80:3128
193.233.229.254:8085
93.120.32.159:9343
94.154.127.227:8085
193.233.141.44:8085
185.77.221.67:8085
154.201.44.241:3128
104.244.75.218:8080
213.166.78.67:8085
154.201.41.252:3128
94.231.216.142:8085
187.44.167.78:60786
142.252.198.168:3128
172.252.224.18:3128
138.91.159.185:80
185.77.221.217:8085
94.231.216.249:8085
213.166.77.60:8085
185.77.220.109:8085
109.94.220.170:8085
172.252.231.72:3128
193.31.126.215:8085
85.208.209.206:8085
154.201.42.20:3128
193.233.83.64:8085
154.201.44.51:3128
213.166.78.131:8085
85.239.37.49:8085
175.100.64.127:9812
142.252.26.159:3128
213.166.78.205:8085
154.201.42.148:3128
157.230.34.152:40765
193.233.141.133:8085
77.83.86.197:8085
95.164.235.168:6224
37.44.254.120:8085
61.29.96.146:8000
138.128.15.102:3128
85.209.149.231:8085
193.233.140.34:8085
185.77.221.112:8085
77.70.35.87:37475
193.56.64.241:8085
193.31.126.119:8085
154.201.44.206:3128
41.170.12.92:37444
88.218.67.58:8085
154.201.45.229:3128
104.252.179.70:3128
172.252.231.83:3128
34.87.84.105:80
154.201.39.71:3128
85.239.37.131:8085
172.252.224.244:3128
217.145.226.142:8085
185.77.221.89:8085
142.252.198.167:3128
191.252.178.3:80
166.88.122.29:3128
172.252.224.130:3128
185.77.220.121:8085
85.208.211.20:8085
45.130.60.53:9580
104.252.179.169:3128
193.233.137.163:8085
172.105.113.133:80
83.171.255.169:8085
213.166.78.86:8085
103.117.192.14:80
122.155.165.191:3128
119.18.158.137:8080
139.228.90.162:8080
178.254.24.12:3128
80.191.162.2:514
159.65.133.175:31280
181.78.64.39:999
173.212.224.134:3129
179.49.157.24:999
181.205.173.74:8080
168.205.102.26:8080
43.243.174.3:83
103.76.12.42:8181
122.3.41.154:8090
181.224.207.20:999
103.159.221.7:3125
47.242.84.173:3128
195.135.242.141:8081
82.179.248.248:80
85.14.243.31:3128
95.216.194.46:1080
45.5.57.124:8080
143.198.242.86:8048
23.94.98.201:8080
161.117.89.36:8888
47.242.48.178:3128
45.189.113.142:999
120.89.91.226:3180
157.100.53.99:999
210.212.227.67:3128
201.222.45.64:999
190.107.224.150:3128
181.224.207.21:999
194.233.88.38:3128
122.102.118.83:8080
190.120.248.157:999
156.200.113.178:1976
45.225.123.92:45005
110.74.195.65:55443
191.97.37.209:23500
191.97.16.115:999
120.89.90.250:3125
157.100.53.100:999
45.149.41.237:41890
103.175.236.102:8080
103.231.78.36:80
180.211.179.126:8080
103.47.175.161:83
20.47.108.204:8888
154.12.243.75:80
43.255.113.232:8082
5.161.105.105:80
93.123.226.23:81
121.156.109.108:8080
14.139.120.236:80
74.205.128.200:80
45.176.96.34:999
41.65.236.58:1976
191.97.16.111:999
80.243.158.6:8080
103.111.59.14:80
        ';
    }
}
