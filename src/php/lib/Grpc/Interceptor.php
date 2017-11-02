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


class Interceptor{
  public function UnaryUnary($method, $argument, $deserialize,
                             array $metadata = [], array $options = [], $continuation){
    return $continuation($method, $argument, $deserialize, $metadata, $options);
  }

  public function StreamUnary($method, $deserialize, array $metadata = [],
                              array $options = [], $continuation){
    return $continuation($method, $deserialize, $metadata, $options);
  }

  public function UnaryStream($method, $argument, $deserialize,
                              array $metadata = [], array $options = [], $continuation){
    return $continuation($method, $argument, $deserialize, $metadata, $options);
  }

  public function StreamStream($method, $deserialize,
                               array $metadata = [], array $options = [], $continuation){
    return $continuation($method, $deserialize, $metadata, $options);
  }
}

class InterceptorChannel {
  private $next = null;
  private $interceptor;

  public function __construct($channel, Interceptor $interceptor) {
    $check_channel = $channel;
    while($check_channel){
      if(is_a($check_channel, 'Grpc\Channel')){
        break;
      }
      $check_channel = $check_channel->getNext();
    }
    if(!is_a($check_channel, 'Grpc\Channel')){
      throw new \Exception("Error: channel argument should wrap a Grpc\Channel inside");
    }
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

  public function getTarget() {
    return $this->getNext()->getTarget();
  }

  public function watchConnectivityState($new_state, $deadline){
    return $this->getNext()->watchConnectivityState($new_state, $deadline);
  }

  public function getConnectivityState($try_to_connect = false){
    return $this->getNext()->getConnectivityState($try_to_connect);
  }

  public function close(){
    return $this->getNext()->close();
  }

  public static function intercept($channel, Interceptor $interceptor){
    return new InterceptorChannel($channel, $interceptor);
  }
}


