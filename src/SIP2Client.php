<?php
namespace NYPL\Services;

use NYPL\Starter\APIException;
use NYPL\Starter\Config;
use PHPUnit\Framework\Exception;
use sip2;

class SIP2Client
{
    /**
     * @var \sip2
     */
    protected $sip2Client;

    public function __construct()
    {
        $this->initializeSip2Client();
    }

    /**
     * @throws APIException
     * @return \sip2
     */
    public function getSip2Client()
    {
        return $this->sip2Client;
    }

    /**
     * @param \sip2 $sip2Client
     */
    public function setSip2Client(\sip2 $sip2Client)
    {
        $this->sip2Client = $sip2Client;
    }

    /**
     * @throws APIException
     * @return \sip2
     */
    protected function initializeSip2Client()
    {
        $sipClient = new sip2();

        try {
            $sipClient->hostname = Config::get('SIP2_HOSTNAME');
            $sipClient->port = Config::get('SIP2_PORT');

            $sipClient->connect();

            $sipClient->AC = Config::get('SIP2_TERMINAL_PASSWORD', null, true);

            $this->setSip2Client($sipClient);
        } catch (\Exception $exception) {
            throw new APIException(
                $exception->getMessage(),
                $exception
            );
        }
    }
}
