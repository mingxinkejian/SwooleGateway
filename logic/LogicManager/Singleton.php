<?

namespace Logic\LogicManager;
/**
* 
*/
class Singleton
{
    private static $_instance;

    public static function getInstance()
    {
        if (self::$_instance == null) {
            $class = get_called_class();
            self::$_instance = new $class;
            self::$_instance->init();
        }

        return self::$_instance;
    }
}