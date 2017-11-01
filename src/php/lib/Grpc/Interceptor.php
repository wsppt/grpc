<?php
/*
 *
 * Copyright 2017 gRPC authors.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace Grpc;

class InterceptorHelper{
  public static function intercept($channel, Interceptor $interceptor){
    return new InterceptorChannel($channel, $interceptor);
  }
}

class Interceptor{
  public function UnaryUnary($method, $argument, $deserialize,
                             array $metadata = [], array $options = [], $call_factory){
    return $call_factory($method, $argument, $deserialize, $metadata, $options);
  }

  public function StreamUnary($method, $deserialize, array $metadata = [],
                              array $options = [], $call_factory){
    return $call_factory($method, $deserialize, $metadata, $options);
  }

  public function UnaryStream($method, $argument, $deserialize,
                              array $metadata = [], array $options = [], $call_factory){
    return $call_factory($method, $argument, $deserialize, $metadata, $options);
  }

  public function StreamStream($method, $deserialize,
                               array $metadata = [], array $options = [], $call_factory){
    return $call_factory($method, $deserialize, $metadata, $options);
  }
}

class InterceptorChannel {
  private $next = null;
  private $interceptor;

  public function __construct($channel, $interceptor = null) {
    $this->next = $channel;
    if($interceptor) {
      $this->interceptor = $interceptor;
    } else {
      $this->interceptor = new Interceptor();
    }
  }

  public function getNext() {
    return $this->next;
  }

  public function getInterceptor() {
    return $this->interceptor;
  }

  public function setNext($next) {
    $this->next = $next;
  }

  public function setInterceptor($interceptor) {
    $this->interceptor = $interceptor;
  }

  public function getTarget() {
    if(!$this->getNext()){
      throw new \Exception('Error: channel getTarget failed. ' .
        'No Grpc/Channel inside interceptor');
    }
    return $this->getNext()->getTarget();
  }

  public function watchConnectivityState($new_state, $deadline){
    if(!$this->getNext()){
      throw new \Exception('Error: channel watchConnectivityState failed. ' .
        'No Grpc/Channel inside interceptor');
    }
    return $this->getNext()->watchConnectivityState($new_state, $deadline);
  }

  public function getConnectivityState($try_to_connect = false){
    if(!$this->getNext()){
      throw new \Exception('Error: channel getConnectivityState failed. ' .
        'No Grpc/Channel inside interceptor');
    }
    return $this->getNext()->getConnectivityState($try_to_connect);
  }

  public function close(){
    if(!$this->getNext()){
      throw new \Exception('Error: channel close failed. ' .
        'No Grpc/Channel inside interceptor');
    }
    return $this->getNext()->close();
  }
}


