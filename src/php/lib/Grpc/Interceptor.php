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
  public function interceptUnaryUnary($method, $argument, $deserialize,
                             array $metadata = [], array $options = [], $continuation){
    return $continuation($method, $argument, $deserialize, $metadata, $options);
  }

  public function interceptStreamUnary($method, $deserialize, array $metadata = [],
                              array $options = [], $continuation){
    return $continuation($method, $deserialize, $metadata, $options);
  }

  public function interceptUnaryStream($method, $argument, $deserialize,
                              array $metadata = [], array $options = [], $continuation){
    return $continuation($method, $argument, $deserialize, $metadata, $options);
  }

  public function interceptStreamStream($method, $deserialize,
                               array $metadata = [], array $options = [], $continuation){
    return $continuation($method, $deserialize, $metadata, $options);
  }
}

class InterceptorChannel {
  private $next = null;
  private $interceptor;

  /**
   * @param Channel|InterceptorChannel $channel An already created Channel
   * or InterceptorChannel object (optional)
   * @param Interceptor|Array  $interceptor
   */
  public function __construct($channel, $interceptors) {
    if($interceptors) {
      if(is_array($interceptors)){
        // remove the first element of the $interceptor
        $interceptor = array_shift($interceptors);
        $len = count($interceptors);
        if($len) {
          $channel = new InterceptorChannel($channel, $interceptors);
        }
      } else {
        $interceptor = $interceptors;
      }
    }
    $this->interceptor = $interceptor;
    $this->next = $channel;
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

  public static function intercept($channel, $interceptor){
    return new InterceptorChannel($channel, $interceptor);
  }
}


