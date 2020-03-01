<?php

namespace Lather;

use SoapVar;
use stdClass;
use Exception;
use SoapClient;
use SoapHeader;
use JsonSerializable;
use Lather\Macros\Filterable;

class Lather implements JsonSerializable
{
    use Filterable;

    /**
     * Soap Client.
     *
     * @var SoapClient
     */
    protected $client;

    /**
     * WSDL URL.
     *
     * @var string
     */
    protected $wsdl;

    /**
     * Function name to be called if empty the class name will be used.
     *
     * @var string
     */
    protected $functionName;

    /**
     * Array of soap params.
     *
     * @var array
     */
    protected $params;

    /**
     * Array of casts for returned responses.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Array of headers to be set in SOAP client.
     *
     * Example:
     * [0 => [
     * 'namespace' => 'ns1',
     * 'name' => 'hello',
     * 'data' => [
     *      'username' => 'hi',
     *      'password' => 'abc123']
     * ]]
     *
     * @var array
     */
    protected $headers = [];

    /**
     * Array of joins (other functions to be called).
     *
     * @var array
     */
    protected $joins = [];

    /**
     * Array of runtime macros.
     *
     * @var array
     */
    private $runtimeMacros = [];

    /**
     * Array containing the formatted reponse.
     *
     * @var array
     */
    private $formattedResponse = [];

    public function __construct()
    {
        $this->client = new SoapClient($this->wsdl);

        $this->setSoapHeaders();

        $this->addRuntimeMacros();

        $this->addClassParams();
    }

    /**
     * Set SOAP headers if headers array is not empty.
     */
    private function setSoapHeaders()
    {
        if (!empty($this->headers)) {
            $headers = [];

            foreach ($this->headers as $header) {
                $namespace = $header['namespace'];
                $name = $header['name'];
                $varData = new stdClass();
                foreach ($header['data'] as $key => $data) {
                    $varData->{$key} = $data;
                }
                $dataValues = new SoapVar($varData, SOAP_ENC_OBJECT);
                $headers[] = new SoapHeader($namespace, $name, $dataValues);
            }

            $this->client->__setSoapHeaders($headers);
        }
    }

    /**
     * Add the SOAP params on construct.
     */
    public function addClassParams()
    {
        foreach ($this->params as $key => $value) {
            $this->{$key} = '';
        }
    }

    /**
     * Boot any runtime macros that have a function starting with 'boot' e.g. bootRest().
     */
    protected function addRuntimeMacros()
    {
        // There are probably much cleaner ways of doing this!
        $classMethods = get_class_methods($this);
        $methods = get_class_methods(Lather::class);

        $methods = array_unique(array_merge($classMethods, $methods));

        foreach ($methods as $method) {
            if (strpos($method, 'boot') === 0) {
                if (!in_array($method, $this->runtimeMacros)) {
                    $this->runtimeMacros[] = $method;
                } else {
                    throw new Exception("Runtime macro: {$method} already defined");
                }
            }
        }
    }

    /**
     * Returns the class name as a string for the SOAP call.
     *
     * @return string
     */
    protected function getClassName(): string
    {
        return (string) (new \ReflectionClass(get_class($this)))->getShortName();
    }

    protected function formatResponse($response)
    {
        foreach ($response as $key => $value) {
            if (array_key_exists($key, $this->casts)) {
                $this->formattedResponse[$key] = $this->applyCastsToValue($key, $value);
            } else {
                $this->formattedResponse[$key] = $value;
            }
        }

        $this->applyMacros();

        return $this->formattedResponse;
    }

    /**
     * Apply any runtime macros to the SOAP response.
     */
    private function applyMacros()
    {
        foreach ($this->runtimeMacros as $macro) {
            $this->formattedResponse = call_user_func([$this, $macro], $this->formattedResponse);
        }
    }

    /**
     * Apply any casts set by the user for a specified value.
     *
     * @param string $key
     * @param mixed  $value
     */
    protected function applyCastsToValue($key, $value)
    {
        $castValue = $this->casts[$key];

        switch ($castValue) {
            case 'integer':
            case 'int':
                return (int) $value;
                break;
            case 'string':
                return (string) $value;
                break;
            default:
                return $value;
        }
    }

    /**
     * Call the SOAP function.
     *
     * @param mixed ...$params
     */
    public function call(...$params)
    {
        if (!empty($params)) {
            foreach ($params as $param) {
                foreach ($param as $pKey => $pValue) {
                    if (array_key_exists($pKey, $this->params)) {
                        $preppedParam[$pKey] = $pValue;
                    } else {
                        throw new Exception("Param: {$pKey} does not exist");
                    }
                }
            }
        } else {
            foreach ($this->params as $key => $param) {
                if ($this->{$key}) {
                    $preppedParam[$key] = $this->{$key};
                } else {
                    throw new Exception("Param: $param is missing");
                }
            }
        }

        $soapFunction = empty($this->functionName) ? $this->getClassName() : $this->functionName;

        $soapResp = $this->client->__soapCall($soapFunction, [$preppedParam]);

        $joins = $this->callJoins((array) $soapResp);
        $joinParams = [];

        foreach ($joins as $join) {
            foreach ($join as $key => $value) {
                if (is_object($value)) {
                    $joinParams[$key] = (array) $value;
                } else {
                    $joinParams[$key] = $value;
                }
            }
        }

        return $this->formatResponse(array_merge((array) $soapResp, $joinParams));
    }

    protected function callJoins($soapResp)
    {
        $joinResp = [];

        if (!empty($this->joins)) {
            foreach ($this->joins as $joinKey => $value) {
                if (class_exists($joinKey)) {
                    $joinCondition = explode(',', $value);

                    $joinClass = new $joinKey();
                    $joinClass->{$joinCondition[1]} = $soapResp[$joinCondition[0]];

                    $joinClass->call();

                    $joinResp[] = $joinClass->all();
                } else {
                    throw new Exception("Class: {$joinKey} does not exist");
                }
            }
        }

        return $joinResp;
    }

    public function __get($name)
    {
        return $this->formattedResponse[$name];
    }

    public function __set($name, $value)
    {
        $this->{$name} = $value;
    }

    public function __call($name, $arguments)
    {
        if (method_exists($this, 'macro'.ucfirst($name))) {
            return call_user_func([$this, 'macro'.ucfirst($name)], ...$arguments);
        } else {
            throw new Exception("Method: {$name} does not exist");
        }
    }

    public function all()
    {
        return $this->formattedResponse;
    }

    public function jsonSerialize()
    {
        return $this->formattedResponse;
    }
}
