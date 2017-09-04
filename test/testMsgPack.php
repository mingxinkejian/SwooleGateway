<?
/**
* 
*/
class SerializeData
{
    public $data1;
    public $data2;
    private $data3;
}
$serData = new SerializeData();
$serData->data1 = 'hello';
$serData->data2 = 'world';
$msgpackData = msgpack_pack($serData);
echo strlen($msgpackData) . PHP_EOL;
$unmsgpackData = msgpack_unpack($msgpackData);
var_dump($unmsgpackData);