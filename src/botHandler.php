<?php
declare(strict_types=1);

namespace APP;
use Amp\Mysql\MysqlConfig;
use Amp\Mysql\MysqlConnectionPool;
use Amp\Postgres\PostgresConfig;
use Amp\Postgres\PostgresConnectionPool;
use Amp\Redis\RedisClient;
use APP\Constants\Constants;
use APP\Traits\HandlerTrait;
use APP\Traits\HelperTrait;
use danog\MadelineProto\EventHandler\Plugin\RestartPlugin;
use danog\MadelineProto\SimpleEventHandler;
use danog\MadelineProto\Settings;
use function Amp\Redis\createRedisClient;

class botHandler extends SimpleEventHandler{

    use HandlerTrait , HelperTrait;
    public int $save_id;
    public int $start_time;
    public array $settings = [];
    private MysqlConnectionPool|PostgresConnectionPool|RedisClient $connectionPool;
    public function getReportPeers(): array{
        return [Constants::ADMIN];
    }
    public static function getPlugins(): array{
        return [
            RestartPlugin::class,
        ];
    }

    public function onStart(): void{
        global $localization;
        $this->logger("The bot was started!");
        $db_setting = $this->getSettings()->getDb();
        if($db_setting instanceof Settings\Database\Mysql){
            $config = new MysqlConfig($db_setting->getUri(),user: $db_setting->getUsername(),password: $db_setting->getPassword());
            $this->connectionPool = new MysqlConnectionPool($config);
        }elseif ($db_setting instanceof Settings\Database\Postgres){
            $config = new PostgresConfig($db_setting->getUri(), user: $db_setting->getUsername(),password: $db_setting->getPassword());
            $this->connectionPool = new PostgresConnectionPool($config);
        }elseif ($db_setting instanceof Settings\Database\Redis){
            $this->connectionPool = createRedisClient($db_setting->getUri());
        }

        if(isset($this->settings['save_id'])){
            $this->save_id = $this->settings['save_id'];
        }else $this->save_id = $this->getSelf()['id'] ?? $this->getReportPeers()[0];

        $this->myReport("The bot was started!");

        if(isset($this->settings['local'])) $localization->setLocale($this->settings['local']);
        $this->start_time = time();
    }

    public function __sleep(): array{
        return ["settings"];
    }
}
