<?php

/**
 * TechDivision\LemCacheContainer\Api\MemCacheEntry
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @category  Appserver
 * @package   TechDivision_LemCacheContainer
 * @author    Philipp Dittert <pd@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.appserver.io
 */

namespace TechDivision\LemCacheContainer\Api;

use TechDivision\LemCacheContainer\Api\AbstractMemCacheEntry;

/**
 * This is the default implementation for a memcache/memcached compatible
 * value object that contains the request data for the CRUD methods.
 * 
 * @category   Appserver
 * @package    TechDivision_WebSocketContainer
 * @subpackage Api
 * @author     Philipp Dittert <pd@techdivision.com>
 * @copyright  2014 TechDivision GmbH <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       http://www.appserver.io
 */
class MemCacheEntry extends AbstractMemCacheEntry
{

    /**
     * central method for pushing data into VO object.
     *
     * @param string $request The actual request instance
     * 
     * @return void
     */
    public function push($request)
    {
        // check if the intial connecten is already initiated and only data are expected
        // else parse this request and select fitting action
        if ($this->getRequestAction()) {
            $this->pushData($request);
        } else {
            
            // parse the request data
            $var = $this->parseRequest($request);
            
            // if the request is NOT empty
            if (empty($var)) {
                throw new \Exception('Empty request data found');
            }
                
            // check the action to be invoked
            switch ($var[0]) {
                
                case 'set':
                    $this->setAction($var);
                    break;
                    
                case 'get':
                    $this->getAction($var);
                    break;
                    
                case 'delete':
                    $this->deleteAction($var);
                    break;
                    
                case 'quit':
                    $this->quitAction($var);
                    break;
                    
                default:
                    throw new \Exception("Found unknown request action $var[0]");
                    break;
            }
            
            // clear the request data
            unset($var);
        }
    }

    /**
     * Parse request and return data as array.
     *
     * @param string $request The request string to be parsed
     * 
     * @return array The data found in the request
     */
    protected function parseRequest($request)
    {
        
        // emtpy request or only a new line is not allowed
        if ($request == false || $request == "\n" || $request == "\r\n") {
            return array();
        }

        // strip header from request (in case of a set request e. g.)
        $header = strstr($request, $this->getNewLine(), true);
        $data = substr(
            strstr(
                $request,
                $this->getNewLine()
            ),
            strlen($this->getNewLine())
        );
        
        // try to read action
        $var = explode(' ', trim($header));
        
        //append rest of this request in "data" key
        $var['data'] = $data;

        return $var;
    }

    /**
     * The memcache "get" action (that returns the value
     * with the requested key from the cache).
     * 
     * The array MUST have the following structure:
     * 
     * array(
     *     1  => 'key' // the key to return the value for
     * )
     *
     * @param array $request The actual request instance
     * 
     * @return void
     * @link http://de1.php.net/manual/de/memcached.get.php
     */
    protected function getAction($request)
    {
        $this->setKey($request[1]);
        $this->setRequestAction('get');
        $this->setComplete(true);
    }

    /**
     * The memcache "delete" action (that removes
     * the entry from the cache).
     * 
     * The array MUST have the following structure:
     * 
     * array(
     *     1  => 'key' // the key to delete the value
     * )
     *
     * @param array $request The actual request instance
     * 
     * @return void
     * @link http://de1.php.net/manual/de/memcached.delete.php
     */
    protected function deleteAction($request)
    {
        $this->setKey($request[1]);
        $this->setRequestAction('delete');
        $this->setComplete(true);
    }

    /**
     * The memcache "add" action (that adds the data passed
     * in the array to the cache).
     * 
     * The array MUST have the following structure:
     * 
     * array(
     *     1      => 'key',     // the key to store the value with
     *     2      => 'flag',    // enable/disable compression
     *     3      => 'expire'   // expiration time in seconds
     *     4      => 'bytes'    // number of bytes of the content
     *     'data' => 'data'     // the data to be stored  
     * )
     * 
     * @param array $request The actual request instance
     * 
     * @return void
     * @see \TechDivision\LemCacheContainer\Api\MemCacheEntry::setAction()
     * @link http://de1.php.net/manual/de/memcached.add.php
     */
    protected function addAction($request)
    {
        $this->setAction($request);
    }

    /**
     * The memcache "set" action (that set's the data passed
     * in the array to the cache).
     * 
     * The array MUST have the following structure:
     * 
     * array(
     *     1      => 'key',     // the key to store the value with
     *     2      => 'flag',    // enable/disable compression
     *     3      => 'expire'   // expiration time in seconds
     *     4      => 'bytes'    // number of bytes of the content
     *     'data' => 'data'     // the data to be stored  
     * )
     *
     * @param array $request The actual request instance
     * 
     * @return void
     * @throws \Exception Is thrown if the data contains an invalid flag
     * @throws \Exception Is thrown if the data contains an invalid expiration time
     * @throws \Exception Is thrown if the data has NOT the specified length in byte
     * @link http://de1.php.net/manual/de/memcached.set.php
     */
    protected function setAction($request)
    {
        
        $this->setRequestAction('set');
        $this->setKey($request[1]);

        // validate Flag Value
        if (is_numeric($request[2])) {
            $this->setFlags($request[2]);
        } else {
            throw new \Exception("CLIENT_ERROR bad command line format");
        }

        // validate Expiretime value
        if (is_numeric($request[3])) {
            $this->setExpTime($request[3]);
        } else {
            throw new \Exception("CLIENT_ERROR found invalid expiration time");
        }

        // validate data-length in bytes
        if (is_numeric($request[4])) {
            $this->setBytes($request[4]);
        } else {
            throw new \Exception("CLIENT_ERROR bad data chunk");
        }

        if ($request['data']) {
            $this->pushData($request['data']);
        }
    }

    /**
     * The memcache "quit" action (that closes the client connection).
     *
     * @param array $request The actual request instance
     * 
     * @return void
     */
    protected function quitAction($request)
    {
        $this->setRequestAction('quit');
        $this->setComplete(true);
    }

    /**
     * Method for validating "value" data for "set" and "add" action
     * and check's if bytes value is reached and set state/response.
     *
     * @param string $data The data to push (to the cache)
     * 
     * @return boolean TRUE if the data has the correct length or is empty
     * @throws \Exception Is thrown if the data has NOT the specified length in byte
     */
    protected function pushData($data)
    {
        
        // first check if we are at the string's end
        if ($data == $this->getNewline() && strlen($this->getData()) == $this->getBytes()) {
            
            $this->setComplete(true);
            return true;
            
        } else {
            
            // if not at the end, rtrim the string (cut off whitespace to the right)
            if ($data != $this->getNewLine()) {
                $data = rtrim($data);
            }
            
            // set the data
            $this->setData($data);
            
            // check if data has the specified length
            if (strlen($this->getData()) == $this->getBytes()) {
                
                $this->setComplete(true);
                return true;
                
            // if NOT throw an exception
            } elseif (strlen($this->getData()) > $this->getBytes()) {
                throw new \Exception("CLIENT_ERROR bad data chunk");
            }
        }
    }
}
