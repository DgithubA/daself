<?php declare(strict_types=1);

namespace App;

use App\Helpers;
use App\Constants;
use App\Traits\HelperTrait;
use App\Traits\ServerTrait;
use App\Traits\CommandTrait;
use App\Traits\HandlerTrait;
use App\Database\DatabaseService;

use danog\Loop\GenericLoop;
use danog\AsyncOrm\DbArray;
use danog\AsyncOrm\KeyType;
use danog\AsyncOrm\ValueType;
use danog\MadelineProto\SimpleEventHandler;
use danog\AsyncOrm\Annotations\OrmMappedArray;
use danog\MadelineProto\Settings\Database\Memory;

class BotHandler extends SimpleEventHandler implements \Amp\Http\Server\RequestHandler
{
    use HandlerTrait, HelperTrait, CommandTrait, ServerTrait;

    public int $save_id;
    public int $start_time;
    public array $settings = [];
    private ?DatabaseService $databaseService;
    private \Amp\DeferredCancellation $cancellation;
    #[OrmMappedArray(KeyType::STRING, ValueType::SCALAR)]
    protected DbArray $ormProperty;
    protected ?GenericLoop $loop;

    public function getReportPeers(): array
    {
        return [Constants::ADMIN];
    }

    public function loop(): ?float
    {
        $delaytominute = 3;//The loop is executed 3 seconds before the start of the minute.

        //Refuses to run the loop when the uptime is less than one minute
        if (time() - $this->start_time < 60)
            return 60 - (time() - $this->start_time);

        //stop genloop
        if (isset($this->settings['stop_genloop']))
            return GenericLoop::STOP;

        //Refuses to run the loop more than once a minute
        if (isset($this->settings['lastloop'])) {
            $lastloop = $this->settings['lastloop'];
            if (time() - $lastloop < 60) {
                return 60 - (time() - $lastloop);
            }
            $this->settings['lastloop'] = time();
        }

        $this->myReport("loop at:" . date("Y-m-d H:i:s"));
        return Helpers::secondsToNext(60 - $delaytominute);
    }

    public function onStop(): void
    {
        $this->logger('onStop');
        $this->loop?->stop();
        $this->stopWebServer();
    }

    public function onStart(): void
    {
        global $localization;

        if (!\Amp\File\exists(Constants::DataFolderPath)) {
            \Amp\File\createDirectory(Constants::DataFolderPath);
        }
        $this->cancellation = new \Amp\DeferredCancellation;

        $db_setting = $this->getSettings()->getDb();
        if (!$db_setting instanceof Memory) {
            $this->databaseService = new DatabaseService($db_setting);
        }

        $this->save_id =
            $this->settings['save_id'] ??
            $this->getSelf()['id'] ??
            $this->getReportPeers()[0];

        if (method_exists($this, 'initWebServer')) {
            $this->initWebServer();
            $this->startWebServer();
        }

        $this->logger("The bot was started!");
        $this->myReport("The bot was started!");

        if (isset($this->settings['local'])) {
            $localization->setLocale($this->settings['local']);
        }
        $this->start_time = time();
        if (!isset($this->settings['stop_genloop'])) {
            $this->loop = new GenericLoop([$this, 'loop'], 'loop');
            $this->loop->start();
        }
    }

    public function __sleep(): array
    {
        return ["settings", 'ormProperty'];
    }
}
