<?php

namespace Telegram\Bot;

use BadMethodCallException;
use Illuminate\Support\Traits\Macroable;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\HttpClients\HttpClientInterface;

/**
 * Class Api.
 *
 * @mixin Commands\CommandBus
 */
class Api
{
    use Macroable {
        __call as macroCall;
    }

    use Events\EmitsEvents,
        Traits\Http,
        Traits\CommandsHandler,
        Traits\HasContainer;
    use Methods\Chat,
        Methods\Commands,
        Methods\EditMessage,
        Methods\Game,
        Methods\Get,
        Methods\Location,
        Methods\Message,
        Methods\Passport,
        Methods\Payments,
        Methods\Query,
        Methods\Stickers,
        Methods\Update;

    /** @var string Version number of the Telegram Bot PHP SDK. */
    const VERSION = '3.0.0';

    /** @var string The name of the environment variable that contains the Telegram Bot API Access Token. */
    const BOT_TOKEN_ENV_NAME = 'TELEGRAM_BOT_TOKEN';
    const TELEGRAM_WEBHOOK_URL = 'TELEGRAM_WEBHOOK_URL';

    /**
     * Instantiates a new Telegram super-class object.
     *
     *
     * @param string                   $token             The Telegram Bot API Access Token.
     * @param bool                     $async             (Optional) Indicates if the request to Telegram will be asynchronous (non-blocking).
     * @param HttpClientInterface|null $httpClientHandler (Optional) Custom HTTP Client Handler.
     *
     * @throws TelegramSDKException
     */
    public function __construct($token = null, $url = null, $async = false, $httpClientHandler = null)
    {
        $this->accessToken = $token ?? getenv(static::BOT_TOKEN_ENV_NAME);
        $this->url = $url ?? getenv(static::TELEGRAM_WEBHOOK_URL);
        $this->validateAccessToken();

        if ($async) {
            $this->setAsyncRequest($async);
        }

        $this->httpClientHandler = $httpClientHandler;
    }

    /**
     * Invoke Bots Manager.
     *
     * @param $config
     *
     * @return BotsManager
     */
    public static function manager($config): BotsManager
    {
        return new BotsManager($config);
    }

    /**
     * Magic method to process any dynamic method calls.
     *
     * @param $method
     * @param $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $arguments);
        }

        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], $arguments);
        }

        //If the method does not exist on the API, try the commandBus.
        if (preg_match('/^\w+Commands?/', $method, $matches)) {
            return call_user_func_array([$this->getCommandBus(), $matches[0]], $arguments);
        }

        throw new BadMethodCallException("Method [$method] does not exist.");
    }

    /**
     * @throws TelegramSDKException
     */
    private function validateAccessToken()
    {
        $response = $this->post('getUserProfilePhotos', $params);

        return new UserProfilePhotos($response->getDecodedBody());
    }

    /**
     * Returns basic info about a file and prepare it for downloading.
     *
     * <code>
     * $params = [
     *   'file_id' => '',
     * ];
     * </code>
     *
     * The file can then be downloaded via the link
     * https://api.telegram.org/file/bot<token>/<file_path>,
     * where <file_path> is taken from the response.
     *
     * @link https://core.telegram.org/bots/api#getFile
     *
     * @param array $params
     *
     * @var string  $params ['file_id']
     *
     * @throws TelegramSDKException
     *
     * @return File
     */
    public function getFile(array $params)
    {
        $response = $this->post('getFile', $params);

        return new File($response->getDecodedBody());
    }

    /**
     * Kick a user from a group or a supergroup.
     *
     * In the case of supergroups, the user will not be able to return to the group on their own using
     * invite links etc., unless unbanned first.
     *
     * The bot must be an administrator in the group for this to work.
     *
     * <code>
     * $params = [
     *   'chat_id'              => '',
     *   'user_id'              => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#kickchatmember
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var int        $params ['user_id']
     *
     * @throws TelegramSDKException
     *
     * @return bool
     */
    public function kickChatMember(array $params)
    {
        $this->post('kickChatMember', $params);

        return true;
    }

    /**
     * Unban a previously kicked user in a supergroup.
     *
     * The user will not return to the group automatically, but will be able to join via link, etc.
     *
     * The bot must be an administrator in the group for this to work.
     *
     * <code>
     * $params = [
     *   'chat_id'              => '',
     *   'user_id'              => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#unbanchatmember
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var int        $params ['user_id']
     *
     * @throws TelegramSDKException
     *
     * @return bool
     */
    public function unbanChatMember(array $params)
    {
        $this->post('unbanChatMember', $params);

        return true;
    }

    /**
     * Get up to date information about the chat (current name of the user for one-on-one conversations,
     * current username of a user, group or channel,
     *
     * <code>
     * $params = [
     *   'chat_id'  => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#getchat
     *
     * @param array $params
     *
     * @var string|int  $params ['chat_id'] Unique identifier for the target chat or username of the target supergroup or channel (in the format @channelusername)
     *
     * @throws TelegramSDKException
     *
     * @return Chat
     */
    public function getChat(array $params)
    {
        $response = $this->post('getChat', $params);

        return new Chat($response->getDecodedBody());
    }

    /**
     * Get a list of administrators in a chat.
     *
     * <code>
     * $params = [
     *   'chat_id'  => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#getchatadministrators
     *
     * @param array $params
     *
     * @var string|int  $params ['chat_id'] Unique identifier for the target chat or username of the target supergroup or channel (in the format @channelusername);
     *
     * @throws TelegramSDKException
     *
     * @return ChatMember[]
     */
    public function getChatAdministrators(array $params)
    {
        $response = $this->post('getChatAdministrators', $params);

        return collect($response->getResult())
            ->map(function ($admin) {
                return new ChatMember($admin);
            })
            ->all();
    }

    /**
     * Get the number of members in a chat
     *
     * <code>
     * $params = [
     *   'chat_id'  => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#getchatmemberscount
     *
     * @param array $params
     *
     * @var string|int  $params ['chat_id'] Unique identifier for the target chat or username of the target supergroup or channel (in the format @channelusername)
     *
     * @throws TelegramSDKException
     *
     * @return int
     */
    public function getChatMembersCount(array $params)
    {
        $response = $this->post('getChatMembersCount', $params);

        return $response->getResult();
    }

    /**
     * Get information about a member of a chat.
     *
     * <code>
     * $params = [
     *   'chat_id'  => '',
     *   'user_id'  => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#getchatmember
     *
     * @param array $params
     *
     * @var string|int  $params ['chat_id'] Unique identifier for the target chat or username of the target supergroup or channel (in the format @channelusername)
     * @var int         $params ['user_id'] Unique identifier of the target user.
     *
     * @throws TelegramSDKException
     *
     * @return ChatMember
     */
    public function getChatMember(array $params)
    {
        $response = $this->post('getChatMember', $params);

        return new ChatMember($response->getDecodedBody());
    }

    /**
     * Send answers to callback queries sent from inline keyboards.
     *
     * he answer will be displayed to the user as a notification at the top of the chat screen or as an alert.
     *
     * <code>
     * $params = [
     *   'callback_query_id'  => '',
     *   'text'               => '',
     *   'show_alert'         => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#answerCallbackQuery
     *
     * @param array $params
     *
     * @var string  $params ['callback_query_id']
     * @var string  $params ['text']
     * @var bool    $params ['show_alert']
     *
     * @throws TelegramSDKException
     *
     * @return bool
     */
    public function answerCallbackQuery(array $params)
    {
        $this->post('answerCallbackQuery', $params);

        return true;
    }


    /**
     * Edit text messages sent by the bot or via the bot (for inline bots).
     *
     * <code>
     * $params = [
     *   'chat_id'                  => '',
     *   'message_id'               => '',
     *   'inline_message_id'        => '',
     *   'text'                     => '',
     *   'parse_mode'               => '',
     *   'disable_web_page_preview' => '',
     *   'reply_markup'             => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#editMessageText
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var int        $params ['message_id']
     * @var string     $params ['inline_message_id']
     * @var string     $params ['text']
     * @var string     $params ['parse_mode']
     * @var bool       $params ['disable_web_page_preview']
     * @var string     $params ['reply_markup']
     *
     * @throws TelegramSDKException
     *
     * @return Message|bool
     */
    public function editMessageText(array $params)
    {
        $response = $this->post('editMessageText', $params);

        return new Message($response->getDecodedBody());
    }

    /**
     * Edit captions of messages sent by the bot or via the bot (for inline bots).
     *
     * <code>
     * $params = [
     *   'chat_id'                  => '',
     *   'message_id'               => '',
     *   'inline_message_id'        => '',
     *   'caption'                  => '',
     *   'reply_markup'             => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#editMessageCaption
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var int        $params ['message_id']
     * @var string     $params ['inline_message_id']
     * @var string     $params ['caption']
     * @var string     $params ['reply_markup']
     *
     * @throws TelegramSDKException
     *
     * @return Message|bool
     */
    public function editMessageCaption(array $params)
    {
        $response = $this->post('editMessageCaption', $params);

        return new Message($response->getDecodedBody());
    }

    /**
     * Edit only the reply markup of messages sent by the bot or via the bot (for inline bots).
     *
     * <code>
     * $params = [
     *   'chat_id'                  => '',
     *   'message_id'               => '',
     *   'inline_message_id'        => '',
     *   'reply_markup'             => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#editMessageReplyMarkup
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var int        $params ['message_id']
     * @var string     $params ['inline_message_id']
     * @var string     $params ['reply_markup']
     *
     * @throws TelegramSDKException
     *
     * @return Message|bool
     */
    public function editMessageReplyMarkup(array $params)
    {
        $response = $this->post('editMessageReplyMarkup', $params);

        return new Message($response->getDecodedBody());
    }

    /**
     * Use this method to send answers to an inline query.
     *
     * <code>
     * $params = [
     *   'inline_query_id'      => '',
     *   'results'              => [],
     *   'cache_time'           => 0,
     *   'is_personal'          => false,
     *   'next_offset'          => '',
     *   'switch_pm_text'       => '',
     *   'switch_pm_parameter'  => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#answerinlinequery
     *
     * @param array     $params
     *
     * @var string      $params ['inline_query_id']
     * @var array       $params ['results']
     * @var int|null    $params ['cache_time']
     * @var bool|null   $params ['is_personal']
     * @var string|null $params ['next_offset']
     * @var string|null $params ['switch_pm_text']
     * @var string|null $params ['switch_pm_parameter']
     *
     * @throws TelegramSDKException
     *
     * @return bool
     */
    public function answerInlineQuery(array $params = [])
    {
        if (is_array($params['results'])) {
            $params['results'] = json_encode($params['results']);
        }

        $this->post('answerInlineQuery', $params);

        return true;
    }

    /**
     * Set a Webhook to receive incoming updates via an outgoing webhook.
     *
     * <code>
     * $params = [
     *   'url'         => '',
     *   'certificate' => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#setwebhook
     *
     * @param array $params
     *
     * @var string  $params ['url']         HTTPS url to send updates to.
     * @var string  $params ['certificate'] Upload your public key certificate so that the root certificate in
     *                                      use can be checked.
     *
     * @throws TelegramSDKException
     *
     * @return TelegramResponse
     */
    public function setWebhook(array $params)
    {
        if (filter_var($params['url'], FILTER_VALIDATE_URL) === false) {
            throw new TelegramSDKException('Invalid URL Provided');
        }

        if (parse_url($params['url'], PHP_URL_SCHEME) !== 'https') {
            throw new TelegramSDKException('Invalid URL, should be a HTTPS url.');
        }

        return $this->uploadFile('setWebhook', $params);
    }

    /**
     * Returns a webhook update sent by Telegram.
     * Works only if you set a webhook.
     *
     * @see setWebhook
     *
     * @return Update
     */
    public function getWebhookUpdate($shouldEmitEvent = true)
    {
        $body = json_decode(file_get_contents('php://input'), true);

        $update = new Update($body);

        if ($shouldEmitEvent) {
            $this->emitEvent(new UpdateWasReceived($update, $this));
        }

        return $update;
    }

    /**
     * Alias for getWebhookUpdate
     *
     * @deprecated Call method getWebhookUpdate (note lack of letter s at end)
     *             To be removed in next major version.
     *
     * @param bool $shouldEmitEvent
     *
     * @return Update
     */
    public function getWebhookUpdates($shouldEmitEvent = true)
    {
        return $this->getWebhookUpdate($shouldEmitEvent);
    }

    /**
     * Removes the outgoing webhook (if any).
     *
     * @throws TelegramSDKException
     *
     * @return TelegramResponse
     */
    public function removeWebhook()
    {
        $url = '';

        return $this->post('setWebhook', compact('url'));
    }

    /**
     * Use this method to receive incoming updates using long polling.
     *
     * <code>
     * $params = [
     *   'offset'  => '',
     *   'limit'   => '',
     *   'timeout' => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#getupdates
     *
     * @param array  $params
     * @param bool   $shouldEmitEvents
     * @var int|null $params ['offset']
     * @var int|null $params ['limit']
     * @var int|null $params ['timeout']
     *
     * @throws TelegramSDKException
     *
     * @return Update[]
     */
    public function getUpdates(array $params = [], $shouldEmitEvents = true)
    {
        $response = $this->post('getUpdates', $params);

        return collect($response->getResult())
            ->map(function ($data) use ($shouldEmitEvents) {

                $update = new Update($data);

                if ($shouldEmitEvents) {
                    $this->emitEvent(new UpdateWasReceived($update, $this));
                }

                return $update;
            })
            ->all();
    }


    /**
     * Builds a custom keyboard markup.
     *
     * <code>
     * $params = [
     *   'keyboard'          => '',
     *   'resize_keyboard'   => '',
     *   'one_time_keyboard' => '',
     *   'selective'         => '',
     * ];
     * </code>
     *
     * @deprecated Use Telegram\Bot\Keyboard\Keyboard::make(array $params = []) instead.
     *             To be removed in next major version.
     *
     * @link       https://core.telegram.org/bots/api#replykeyboardmarkup
     *
     * @param array $params
     *
     * @var array   $params ['keyboard']
     * @var bool    $params ['resize_keyboard']
     * @var bool    $params ['one_time_keyboard']
     * @var bool    $params ['selective']
     *
     * @return string
     */
    public function replyKeyboardMarkup(array $params)
    {
        return Keyboard::make($params);
    }

    /**
     * Hide the current custom keyboard and display the default letter-keyboard.
     *
     * <code>
     * $params = [
     *   'hide_keyboard' => true,
     *   'selective'     => false,
     * ];
     * </code>
     *
     * @deprecated Use Telegram\Bot\Keyboard\Keyboard::hide(array $params = []) instead.
     *             To be removed in next major version.
     *
     * @link       https://core.telegram.org/bots/api#replykeyboardhide
     *
     * @param array $params
     *
     * @var bool    $params ['hide_keyboard']
     * @var bool    $params ['selective']
     *
     * @return string
     */
    public static function replyKeyboardHide(array $params = [])
    {
        return Keyboard::hide($params);
    }

    /**
     * Display a reply interface to the user (act as if the user has selected the bot‘s message and tapped ’Reply').
     *
     * <code>
     * $params = [
     *   'force_reply' => true,
     *   'selective'   => false,
     * ];
     * </code>
     *
     * @deprecated Use Telegram\Bot\Keyboard\Keyboard::forceReply(array $params = []) instead.
     *             To be removed in next major version.
     *
     * @link       https://core.telegram.org/bots/api#forcereply
     *
     * @param array $params
     *
     * @var bool    $params ['force_reply']
     * @var bool    $params ['selective']
     *
     * @return Keyboard
     */
    public static function forceReply(array $params = [])
    {
        return Keyboard::forceReply($params);
    }

    /**
     * Processes Inbound Commands.
     *
     * @param bool  $webhook
     * @param array $params
     *
     * @return Update|Update[]
     */
    public function commandsHandler($webhook = false, array $params = [])
    {
        if ($webhook) {
            $update = $this->getWebhookUpdate();
            $this->processCommand($update);

            return $update;
        }

        $updates = $this->getUpdates($params);
        $highestId = -1;

        foreach ($updates as $update) {
            $highestId = $update->getUpdateId();
            $this->processCommand($update);
        }

        //An update is considered confirmed as soon as getUpdates is called with an offset higher than its update_id.
        if ($highestId != -1) {
            $params = [];
            $params['offset'] = $highestId + 1;
            $params['limit'] = 1;
            $this->markUpdateAsRead($params);
        }

        return $updates;
    }

    /**
     * Check update object for a command and process.
     *
     * @param Update $update
     */
    public function processCommand(Update $update)
    {
        $message = $update->getMessage();

        if ($message !== null && $message->has('text')) {
            $this->getCommandBus()->handler($message->getText(), $update);
        }
    }

    /**
     * Helper to Trigger Commands.
     *
     * @param string $name   Command Name
     * @param Update $update Update Object
     *
     * @return mixed
     */
    public function triggerCommand($name, Update $update)
    {
        return $this->getCommandBus()->execute($name, $update->getMessage()->getText(), $update);
    }

    /**
     * Determine if a given type is the message.
     *
     * @deprecated Call method isType directly on Message object
     *             To be removed in next major version.
     *
     * @param string         $type
     * @param Update|Message $object
     *
     * @throws \ErrorException
     *
     */
    public function isMessageType($type, $object)
    {
        trigger_error('This method has been deprecated. Use isType() on the Message object instead.', E_USER_DEPRECATED);
    }

    /**
     * Detect Message Type Based on Update or Message Object.
     *
     * @deprecated Call method detectType directly on Message object
     *             To be removed in next major version.
     *
     * @param Update|Message $object
     *
     * @throws \ErrorException
     *
     * @return string|null
     */
    public function detectMessageType($object)
    {
        trigger_error('This method has been deprecated. Use detectType() on the Message object instead.', E_USER_DEPRECATED);
    }

    /**
     * Sends a GET request to Telegram Bot API and returns the result.
     *
     * @param string $endpoint
     * @param array  $params
     *
     * @throws TelegramSDKException
     *
     * @return TelegramResponse
     */
    protected function get($endpoint, $params = [])
    {
        if (array_key_exists('reply_markup', $params)) {
            $params['reply_markup'] = (string)$params['reply_markup'];
        }

        return $this->sendRequest(
            'GET',
            $endpoint,
            $params
        );
    }

    /**
     * Sends a POST request to Telegram Bot API and returns the result.
     *
     * @param string $endpoint
     * @param array  $params
     * @param bool   $fileUpload Set true if a file is being uploaded.
     *
     * @return TelegramResponse
     */
    protected function post($endpoint, array $params = [], $fileUpload = false)
    {
        if ($fileUpload) {
            $params = ['multipart' => $params];
        } else {

            if (array_key_exists('reply_markup', $params)) {
                $params['reply_markup'] = (string)$params['reply_markup'];
            }

            $params = ['form_params' => $params];
        }

        return $this->sendRequest(
            'POST',
            $endpoint,
            $params
        );
    }

    /**
     * Sends a multipart/form-data request to Telegram Bot API and returns the result.
     * Used primarily for file uploads.
     *
     * @param string $endpoint
     * @param array  $params
     *
     * @throws TelegramSDKException
     *
     * @return TelegramResponse
     */
    protected function uploadFile($endpoint, array $params = [])
    {
        $multipart_params = collect($params)
            ->reject(function ($value) {
                return is_null($value);
            })
            ->map(function ($contents, $name) {

                if (!is_resource($contents) && $this->isValidFileOrUrl($name, $contents)) {
                    $contents = (new InputFile($contents))->open();
                }

                return [
                    'name'     => $name,
                    'contents' => $contents,
                ];
            })
            //Reset the keys on the collection
            ->values()
            ->all();

        return $this->post($endpoint, $multipart_params, true);
    }

    /**
     * Sends a request to Telegram Bot API and returns the result.
     *
     * @param string $method
     * @param string $endpoint
     * @param array  $params
     *
     * @throws TelegramSDKException
     *
     * @return TelegramResponse
     */
    protected function sendRequest(
        $method,
        $endpoint,
        array $params = []
    ) {
        $request = $this->request($method, $endpoint, $params);

        return $this->lastResponse = $this->client->sendRequest($request);
    }

    /**
     * Instantiates a new TelegramRequest entity.
     *
     * @param string $method
     * @param string $endpoint
     * @param array  $params
     *
     * @return TelegramRequest
     */
    protected function request(
        $method,
        $endpoint,
        array $params = []
    ) {
        return new TelegramRequest(
            $this->getAccessToken(),
            $method,
            $endpoint,
            $params,
            $this->isAsyncRequest(),
            $this->getTimeOut(),
            $this->getConnectTimeOut()
        );
    }

    /**
     * Magic method to process any "get" requests.
     *
     * @param $method
     * @param $arguments
     *
     * @throws TelegramSDKException
     *
     * @return bool|TelegramResponse|UnknownObject
     */
    public function __call($method, $arguments)
    {
        if (preg_match('/^\w+Commands?/', $method, $matches)) {
            return call_user_func_array([$this->getCommandBus(), $matches[0]], $arguments);
        }

        $action = substr($method, 0, 3);
        if ($action === 'get') {
            /* @noinspection PhpUndefinedFunctionInspection */
            $class_name = Str::studly(substr($method, 3));
            $class = 'Telegram\Bot\Objects\\'.$class_name;
            $response = $this->post($method, $arguments[0] ?: []);

            if (class_exists($class)) {
                return new $class($response->getDecodedBody());
            }

            return $response;
        }
        $response = $this->post($method, $arguments[0]);

        return new UnknownObject($response->getDecodedBody());
    }

    /**
     * Set the IoC Container.
     *
     * @param $container Container instance
     *
     * @return void
     */
    public static function setContainer(Container $container)
    {
        self::$container = $container;
    }

    /**
     * Get the IoC Container.
     *
     * @return Container
     */
    public function getContainer()
    {
        return self::$container;
    }

    /**
     * Check if IoC Container has been set.
     *
     * @return boolean
     */
    public function hasContainer()
    {
        return self::$container !== null;
    }

    /**
     * @return int
     */
    public function getTimeOut()
    {
        return $this->timeOut;
    }

    /**
     * @param int $timeOut
     *
     * @return $this
     */
    public function setTimeOut($timeOut)
    {
        $this->timeOut = $timeOut;

        return $this;
    }

    /**
     * @return int
     */
    public function getConnectTimeOut()
    {
        return $this->connectTimeOut;
    }

    /**
     * @param int $connectTimeOut
     *
     * @return $this
     */
    public function setConnectTimeOut($connectTimeOut)
    {
        $this->connectTimeOut = $connectTimeOut;

        return $this;
    }

    /**
     * An alias for getUpdates that helps readability.
     *
     * @param $params
     *
     * @return Objects\Update[]
     */
    protected function markUpdateAsRead($params)
    {
        return $this->getUpdates($params, false);
    }

    /**
     * Determines if the string passed to be uploaded is a valid
     * file on the local file system, or a valid remote URL.
     *
     * @param string $name
     * @param string $contents
     *
     * @return bool
     */
    protected function isValidFileOrUrl($name, $contents)
    {
        //Don't try to open a url as an actual file when using this method to setWebhook.
        if ($name == 'url') {
            return false;
        }

        //If a certificate name is passed, we must check for the file existing on the local server,
        // otherwise telegram ignores the fact it wasn't sent and no error is thrown.
        if ($name == 'certificate') {
            return true;
        }

        //Is the content a valid file name.
        if (is_readable($contents)) {
            return true;
        }

        //Is the content a valid URL
        return filter_var($contents, FILTER_VALIDATE_URL);
        if (! $this->accessToken || ! is_string($this->accessToken)) {
            throw TelegramSDKException::tokenNotProvided(static::BOT_TOKEN_ENV_NAME);
        }
    }
}
