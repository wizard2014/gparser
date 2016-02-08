<?php

namespace Application\Service;

use Zend\Http\Client as HttpClient;
use Zend\Http\PhpEnvironment\Request as PhpEnvironment;
use Zend\Dom\Document;
use Zend\Dom\Document\Query;

class EmailListService
{
    private $cookiesfile;

    public function __construct()
    {
        $this->cookiesfile = tempnam(sys_get_temp_dir(), 'cookies_');
    }

    /**
     * Get email's list
     *
     * @param $email
     * @param $password
     *
     * @return string
     */
    public function getEmailList($email, $password)
    {
        $client = new HttpClient();
        $client->setAdapter(\Zend\Http\Client\Adapter\Curl::class);

        $config = $this->setCurlOpt([
            CURLOPT_CONNECTTIMEOUT  => 30,
            CURLOPT_USERAGENT       => (new PhpEnvironment())->getServer('HTTP_USER_AGENT'),
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_FOLLOWLOCATION  => 1,
            CURLOPT_HEADER          => 0,
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_CONNECTTIMEOUT  => 120,
            CURLOPT_TIMEOUT         => 120
        ]);
        $client->setOptions($config);
        $client->setUri('https://accounts.google.com/ServiceLogin');
        $data = $client->send();

        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        $formFields = $this->getFormFields($data);

        $formFields['Email']  = $email;
        $formFields['Passwd'] = $password;
        unset($formFields['PersistentCookie']);

        $postString = '';
        foreach ($formFields as $key => $value) {
            $postString .= $key . '=' . urlencode($value) . '&';
        }

        $postString = substr($postString, 0, -1);

        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        $config = $this->setCurlOpt([
            CURLOPT_POST            => 1,
            CURLOPT_REFERER         => 'https://mail.google.com/',
            CURLOPT_POSTFIELDS      => $postString
        ]);
        $client->setOptions($config);
        $client->setUri('https://accounts.google.com/ServiceLogin?service=mail&passive=true&rm=false&continue=https://mail.google.com/mail/&ss=1&scc=1&ltmpl=default&ltmplcache=2');
        $client->send();

        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        $config = $this->setCurlOpt([
            CURLOPT_POST            => 0,
            CURLOPT_POSTFIELDS      => null
        ]);
        $client->setOptions($config);
        $client->setUri('https://mail.google.com/mail/h/jeu23doknfnj/?zy=e&f=1');
        $html = $client->send();

        $body = $html->getBody();

        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        $document = new Document($body);
        $nodeList = Query::execute('table.th tr td', $document, Query::TYPE_CSS);

        $result = [];

        foreach ($nodeList as $node) {
            $nodeValue = trim(str_replace('&nbsp;', '', htmlentities($node->nodeValue)));

            if (empty($nodeValue)) { continue; }

            $result[] = $nodeValue;
        }

        return array_chunk($result, 3);
    }

    /**
     * Get email details
     *
     * @param array $search
     *
     * @return array
     */
    public function getEmail(array $search)
    {
        $result = [];

        $client = new HttpClient();
        $client->setAdapter(\Zend\Http\Client\Adapter\Curl::class);

        $config = $this->setCurlOpt([
            CURLOPT_REFERER => 'https://mail.google.com/',
        ]);

        $client->setOptions($config);

        foreach ($search as $link) {
            $client->setUri('https://mail.google.com/mail/u/0/h/lbdmf5y3fej7/' . $link);
            $data = $client->send();

            $result[] = $data->getBody();
        }

        return $result;
    }

    /**
     * Base curl options
     *
     * @return mixed
     */
    protected function getBaseCurlOpt()
    {
        $opt['curloptions'] = [
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_COOKIEJAR       => $this->cookiesfile,
            CURLOPT_COOKIEFILE      => $this->cookiesfile,
        ];

        return $opt;
    }

    /**
     * Set curl options
     *
     * @param array $options
     *
     * @return mixed
     */
    protected function setCurlOpt(array $options)
    {
        $opt = $this->getBaseCurlOpt();

        foreach ($options as $key => $value) {
            $opt['curloptions'][$key] = $value;
        }

        return $opt;
    }

    /**
     * Get form fields
     *
     * @param $data
     *
     * @return array
     * @throws \Exception
     */
    protected function getFormFields($data)
    {
        if (preg_match('/(<form.*?id=.?gaia_loginform.*?<\/form>)/is', $data, $matches)) {
            $inputs = $this->getInputs($matches[1]);

            return $inputs;
        } else {
            throw new \Exception('Cannot find login form');
        }
    }

    /**
     * Get data from input
     *
     * @param $form
     *
     * @return array
     */
    protected function getInputs($form)
    {
        $inputs = [];

        $elements = preg_match_all('/(<input[^>]+>)/is', $form, $matches);

        if ($elements > 0) {
            for ($i = 0; $i < $elements; $i++) {
                $el = preg_replace('/\s{2,}/', ' ', $matches[1][$i]);

                if (preg_match('/name=(?:["\'])?([^"\'\s]*)/i', $el, $name)) {
                    $name  = $name[1];
                    $value = '';

                    if (preg_match('/value=(?:["\'])?([^"\'\s]*)/i', $el, $value)) {
                        $value = $value[1];
                    }

                    $inputs[$name] = $value;
                }
            }
        }

        return $inputs;
    }
}
