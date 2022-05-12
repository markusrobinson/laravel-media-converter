<?php

namespace markusrobinson\MediaConverter\Http\Controllers;

use Aws\Sns\Message;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use markusrobinson\MediaConverter\Events\ConversionHasCompleted;
use markusrobinson\MediaConverter\Events\ConversionHasError;
use markusrobinson\MediaConverter\Events\ConversionHasInputInformation;
use markusrobinson\MediaConverter\Events\ConversionHasNewWarning;
use markusrobinson\MediaConverter\Events\ConversionHasStatusUpdate;
use markusrobinson\MediaConverter\Events\ConversionIsProgressing;
use markusrobinson\MediaConverter\Events\ConversionQueueHop;

class IncomingWebhookController extends Controller
{
    public function __construct()
    {
        $this->middleware('verify-signature');
    }

    /**
     * @throws \Exception
     */
    public function __invoke()
    {
        try {
            $message = $this->ensureSubscriptionIsConfirmed();

            Log::info('incoming MediaConvert webhook message', $message);

            if (! array_key_exists('detail', $message)) {
                Log::alert('incoming MediaConvert webhook: "detail"-key does not exist');

                return;
            }

            $detail = $message['detail'];

            if (! array_key_exists('status', $detail)) {
                Log::alert('incoming MediaConvert webhook: "status"-key does not exist');

                return;
            }

            $status = $detail['status'];

            $this->fireEventFor($status, $message);
        } catch (\Exception $e) {
            throw new \Exception($e);
        }
    }

    /**
     * @param $status
     * @param $message
     *
     * @throws \Exception
     */
    public function fireEventFor($status, $message)
    {
        switch ($status) {
            case 'PROGRESSING':
                event(new ConversionIsProgressing($message));
                break;
            case 'INPUT_INFORMATION':
                event(new ConversionHasInputInformation($message));
                break;
            case 'COMPLETE':
                event(new ConversionHasCompleted($message));
                break;
            case 'STATUS_UPDATE':
                event(new ConversionHasStatusUpdate($message));
                break;
            case 'NEW_WARNING':
                event(new ConversionHasNewWarning($message));
                break;
            case 'QUEUE_HOP':
                event(new ConversionQueueHop($message));
                break;
            case 'ERROR':
                event(new ConversionHasError($message));
                break;
            default:
                throw new \Exception();
        }
    }

    /**
     * @return array
     */
    public function ensureSubscriptionIsConfirmed(): array
    {
        $message = Message::fromRawPostData()->toArray();

        if (array_key_exists('SubscribeURL', $message)) {
            Http::get($message['SubscribeURL']);
        }

        return json_decode($message['Message'], true);
    }
}
