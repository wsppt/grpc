<?php
/*
 *
 * Copyright 2015 gRPC authors.
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
/**
 * Interface exported by the server.
 */
require_once(dirname(__FILE__).'/../../lib/Grpc/BaseStub.php');
require_once(dirname(__FILE__).'/../../lib/Grpc/AbstractCall.php');
require_once(dirname(__FILE__).'/../../lib/Grpc/UnaryCall.php');
require_once(dirname(__FILE__).'/../../lib/Grpc/ClientStreamingCall.php');
require_once(dirname(__FILE__).'/../../lib/Grpc/Interceptor.php');

class SimpleRequest{
  private $data;
  public function __construct($data){
    $this->data = $data;
  }
  public function setData($data){
    $this->data = $data;
  }
  public function serializeToString(){
    return $this->data;
  }
}

class InterceptorClient extends Grpc\BaseStub {

  /**
   * @param string $hostname hostname
   * @param array $opts channel options
   * @param \Grpc\Channel or \Grpc\InterceptorChannel $channel (optional) re-use channel object
   */
  public function __construct($hostname, $opts, $channel = null) {
    parent::__construct($hostname, $opts, $channel);
  }

  /**
   * A simple RPC.
   * @param \Routeguide\Point $argument input argument
   * @param array $metadata metadata
   * @param array $options call options
   */
  public function UnaryCall(SimpleRequest $argument,
                            $metadata = [], $options = []) {
    return $this->_simpleRequest('/dummy_method',
      $argument, [], $metadata, $options);
  }

  /**
   * A client-to-server streaming RPC.
   * @param array $metadata metadata
   * @param array $options call options
   */
  public function StreamCall($metadata = [], $options = []) {
    return $this->_clientStreamRequest('/dummy_method',
      [], $metadata, $options);
  }
}


class ChangeMetadataInterceptor extends Grpc\Interceptor{
  public function UnaryUnary($method, $argument, $deserialize, array $metadata = [], array $options = [], $call_factory)
  {
    $metadata["foo"] = array('interceptor_from_unary_request');
    return $call_factory($method, $argument, $deserialize, $metadata, $options);
  }
  public function StreamUnary($method, $deserialize, array $metadata = [], array $options = [], $call_factory)
  {
    $metadata["foo"] = array('interceptor_from_stream_request');
    return $call_factory($method, $deserialize, $metadata, $options);
  }
}

class ChangeMetadataInterceptor2 extends Grpc\Interceptor{
  public function UnaryUnary($method, $argument, $deserialize, array $metadata = [], array $options = [], $call_factory)
  {
    if(array_key_exists('foo', $metadata)){
      $metadata['bar'] = array('ChangeMetadataInterceptor should be execute first');
    } else {
      $metadata["bar"] = array('interceptor_from_unary_request');
    }
    return $call_factory($method, $argument, $deserialize, $metadata, $options);
  }
  public function StreamUnary($method, $deserialize, array $metadata = [], array $options = [], $call_factory)
  {
    if(array_key_exists('foo', $metadata)){
      $metadata['bar'] = array('ChangeMetadataInterceptor should be execute first');
    } else {
      $metadata["bar"] = array('interceptor_from_stream_request');
    }
    return $call_factory($method, $deserialize, $metadata, $options);
  }
}

class ChangeRequestCall {
  private $call;

  public function __construct($call){
    $this->call = $call;
  }
  public function getCall(){
    return $this->call;
  }

  public function write($request){
    $request->setData('intercepted_stream_request');
    $this->getCall()->write($request);
  }

  public function wait(){
    return $this->getCall()->wait();
  }
}

class ChangeRequestInterceptor extends Grpc\Interceptor{
  public function UnaryUnary($method, $argument, $deserialize, array $metadata = [], array $options = [], $call_factory)
  {
    $argument->setData('intercepted_unary_request');
    return $call_factory($method, $argument, $deserialize, $metadata, $options);
  }
  public function StreamUnary($method, $deserialize, array $metadata = [], array $options = [], $call_factory)
  {
    return new ChangeRequestCall(
      $call_factory($method, $deserialize, $metadata, $options));
  }
}

class StopCallInterceptor extends Grpc\Interceptor{
  public function UnaryUnary($method, $argument, $deserialize, array $metadata = [], array $options = [], $call_factory)
  {
    $metadata["foo"] = array('interceptor_from_request_response');
  }
  public function StreamUnary($method, $deserialize, array $metadata = [], array $options = [], $call_factory)
  {
    $metadata["foo"] = array('interceptor_from_request_response');
  }
}

class InterceptorTest extends PHPUnit_Framework_TestCase
{
  public function setUp()
  {
    $this->server = new Grpc\Server([]);
    $this->port = $this->server->addHttp2Port('0.0.0.0:0');
    $this->channel = new Grpc\Channel('localhost:'.$this->port, ['credentials' => Grpc\ChannelCredentials::createInsecure()]);
    $this->server->start();
  }

  public function tearDown()
  {
    $this->channel->close();
  }


  public function testClientChangeMetadataOneInterceptor()
  {
    $req_text = 'client_request';
    $channel_matadata_interceptor = new ChangeMetadataInterceptor();
    $intercept_channel = Grpc\InterceptorHelper::intercept($this->channel, $channel_matadata_interceptor);
    echo "create Client\n";
    $client = new InterceptorClient('localhost:'.$this->port, [
      'credentials' => Grpc\ChannelCredentials::createInsecure(),
    ], $intercept_channel);
    echo "create Call\n";
    $req = new SimpleRequest($req_text);
    echo "Call created\n";
    $unary_call = $client->UnaryCall($req);
    echo "start call\n";
    $event = $this->server->requestCall();
    $this->assertSame('/dummy_method', $event->method);
    $this->assertSame(['interceptor_from_unary_request'], $event->metadata['foo']);

    $stream_call = $client->StreamCall();
    $stream_call->write($req);
    $event = $this->server->requestCall();
    $this->assertSame('/dummy_method', $event->method);
    $this->assertSame(['interceptor_from_stream_request'], $event->metadata['foo']);

    unset($unary_call);
    unset($stream_call);
    unset($server_call);
  }

  public function testClientChangeMetadataTwoInterceptor()
  {
    $req_text = 'client_request';
    $status_text = 'status:client_server_full_response_text';
    $channel_matadata_interceptor = new ChangeMetadataInterceptor();
    $channel_matadata_intercepto2 = new ChangeMetadataInterceptor2();
    $intercept_channel1 = Grpc\InterceptorHelper::intercept($this->channel, $channel_matadata_interceptor);
    $intercept_channel2 = Grpc\InterceptorHelper::intercept($intercept_channel1, $channel_matadata_intercepto2);
    $client = new InterceptorClient('localhost:'.$this->port, [
      'credentials' => Grpc\ChannelCredentials::createInsecure(),
    ], $intercept_channel2);

    $req = new SimpleRequest($req_text);
    $unary_call = $client->UnaryCall($req);
    $event = $this->server->requestCall();
    $this->assertSame('/dummy_method', $event->method);
    $this->assertSame(['interceptor_from_unary_request'], $event->metadata['foo']);
    $this->assertSame(['interceptor_from_unary_request'], $event->metadata['bar']);

    $stream_call = $client->StreamCall();
    $stream_call->write($req);
    $event = $this->server->requestCall();
    $this->assertSame('/dummy_method', $event->method);
    $this->assertSame(['interceptor_from_stream_request'], $event->metadata['foo']);
    $this->assertSame(['interceptor_from_stream_request'], $event->metadata['bar']);

    unset($unary_call);
    unset($stream_call);
    unset($server_call);
  }

  public function testClientChangeRequestInterceptor()
  {
    $req_text = 'client_request';
    $change_request_interceptor = new ChangeRequestInterceptor();
    $intercept_channel = Grpc\InterceptorHelper::intercept($this->channel, $change_request_interceptor);
    $client = new InterceptorClient('localhost:'.$this->port, [
      'credentials' => Grpc\ChannelCredentials::createInsecure(),
    ], $intercept_channel);

    $req = new SimpleRequest($req_text);
    $unary_call = $client->UnaryCall($req);

    $event = $this->server->requestCall();
    $this->assertSame('/dummy_method', $event->method);
    $server_call = $event->call;
    $event = $server_call->startBatch([
      Grpc\OP_SEND_INITIAL_METADATA => [],
      Grpc\OP_SEND_STATUS_FROM_SERVER => [
        'metadata' => [],
        'code' => Grpc\STATUS_OK,
        'details' => '',
      ],
      Grpc\OP_RECV_MESSAGE => true,
      Grpc\OP_RECV_CLOSE_ON_SERVER => true,
    ]);
    $this->assertSame('intercepted_unary_request', $event->message);

    $stream_call = $client->StreamCall();
    $stream_call->write($req);
    $event = $this->server->requestCall();
    $this->assertSame('/dummy_method', $event->method);
    $server_call = $event->call;
    $event = $server_call->startBatch([
      Grpc\OP_SEND_INITIAL_METADATA => [],
      Grpc\OP_SEND_STATUS_FROM_SERVER => [
        'metadata' => [],
        'code' => Grpc\STATUS_OK,
        'details' => '',
      ],
      Grpc\OP_RECV_MESSAGE => true,
      Grpc\OP_RECV_CLOSE_ON_SERVER => true,
    ]);
    $this->assertSame('intercepted_stream_request', $event->message);

    unset($unary_call);
    unset($stream_call);
    unset($server_call);
  }

  public function testClientChangeStopCallInterceptor()
  {
    $req_text = 'client_request';
    $channel_request_interceptor = new StopCallInterceptor();
    $intercept_channel = Grpc\InterceptorHelper::intercept($this->channel, $channel_request_interceptor);
    $client = new InterceptorClient('localhost:'.$this->port, [
      'credentials' => Grpc\ChannelCredentials::createInsecure(),
    ], $intercept_channel);

    $req = new SimpleRequest($req_text);
    $unary_call = $client->UnaryCall($req);
    $this->assertNull($unary_call);


    $stream_call = $client->StreamCall();
    $this->assertNull($stream_call);

    unset($unary_call);
    unset($stream_call);
    unset($server_call);
  }

}
