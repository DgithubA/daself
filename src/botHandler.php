<?php
declare(strict_types=1);

namespace APP;
use APP\Constants\Constants;
use APP\Database\DatabaseService;
use APP\Traits\CommandTrait;
use APP\Traits\HandlerTrait;
use APP\Traits\HelperTrait;
use APP\Traits\ServerTrait;
use danog\MadelineProto\EventHandler\Plugin\RestartPlugin;
use danog\MadelineProto\Settings\Database\Memory;
use danog\MadelineProto\SimpleEventHandler;
use Revolt\EventLoop;


class botHandler extends SimpleEventHandler implements \Amp\Http\Server\RequestHandler{

    use HandlerTrait , HelperTrait ,CommandTrait, ServerTrait;
    public int $save_id;
    public int $start_time;
    public array $settings = [];
    private ?DatabaseService $databaseService;
    private \Amp\DeferredCancellation $cancellation;
    public function getReportPeers(): array{
        return [Constants::ADMIN];
    }
    public static function getPlugins(): array{
        return [
            RestartPlugin::class,
        ];
    }

    public function onStop(): void{
        $this->logger('onStop');
        $this->stopWebServer();
    }

    public function onStart(): void{
        global $localization;
        $this->logger("The bot was started!");
        $this->cancellation = new \Amp\DeferredCancellation();

        $db_setting = $this->getSettings()->getDb();
        if(!$db_setting instanceof Memory) $this->databaseService = new DatabaseService($db_setting);

        if(isset($this->settings['save_id'])){
            $this->save_id = $this->settings['save_id'];
        }else $this->save_id = $this->getSelf()['id'] ?? $this->getReportPeers()[0];

        if(method_exists($this,'initWebServer')) {
            $this->initWebServer();
            $this->startWebServer();
        }

        $this->myReport("The bot was started!");

        if(isset($this->settings['local'])) $localization->setLocale($this->settings['local']);
        $this->start_time = time();
    }

    public function __sleep(): array{
        return ["settings"];
    }
}
