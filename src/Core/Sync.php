<?php
/**
 * Created by PhpStorm.
 * User: HanSon
 * Date: 2017/1/14
 * Time: 11:21.
 */

namespace Hanson\Vbot\Core;

use Hanson\Vbot\Exceptions\SyncCheckException;
use Hanson\Vbot\Exceptions\WebSyncException;
use Hanson\Vbot\Foundation\Vbot;

class Sync
{
    /**
     * @var Vbot
     */
    private $vbot;

    public function __construct(Vbot $vbot)
    {
        $this->vbot = $vbot;
    }

    /**
     * check if got a new message.
     *
     * @throws SyncCheckException
     *
     * @return array
     */
    public function checkSync()
    {
        $url = $this->vbot->config['server.uri.push'].'/synccheck?'.http_build_query([
                'r'        => time(),
                'sid'      => $this->vbot->config['server.sid'],
                'uin'      => $this->vbot->config['server.uin'],
                'skey'     => $this->vbot->config['server.skey'],
                'deviceid' => $this->vbot->config['server.deviceId'],
                'synckey'  => $this->vbot->config['server.syncKeyStr'],
                '_'        => time(),
            ]);

        try {
            $content = $this->vbot->http->get($url, ['timeout' => 35]);

            preg_match('/window.synccheck=\{retcode:"(\d+)",selector:"(\d+)"\}/', $content, $matches);

            return [$matches[1], $matches[2]];
        } catch (\Exception $e) {
            throw new SyncCheckException('sync check:'.$e->getMessage());
        }
    }

    /**
     * get a message.
     *
     * @throws WebSyncException
     *
     * @return mixed|string
     */
    public function sync()
    {
        $url = sprintf($this->vbot->config['server.uri.base'].'/webwxsync?sid=%s&skey=%s&lang=zh_CN&pass_ticket=%s',
            $this->vbot->config['server.sid'],
            $this->vbot->config['server.skey'],
            $this->vbot->config['server.passTicket']
        );

        try {
            $result = $this->vbot->http->json($url, [
                'BaseRequest' => $this->vbot->config['server.baseRequest'],
                'SyncKey'     => $this->vbot->config['server.syncKey'],
                'rr'          => ~time(),
            ], true);

            if ($result['BaseResponse']['Ret'] == 0) {
                $this->generateSyncKey($result);
            }

            return $result;
        } catch (\Exception $e) {
            throw new WebSyncException('web sync:'.$e->getMessage());
        }
    }

    /**
     * generate a sync key.
     *
     * @param $result
     */
    public function generateSyncKey($result)
    {
        $this->vbot->config['server.syncKey'] = $result['SyncKey'];

        $syncKey = [];

        if (is_array($this->vbot->config['server.syncKey.List'])) {
            foreach ($this->vbot->config['server.syncKey.List'] as $item) {
                $syncKey[] = $item['Key'].'_'.$item['Val'];
            }
        }

        $this->vbot->config['server.syncKeyStr'] = implode('|', $syncKey);
    }
}