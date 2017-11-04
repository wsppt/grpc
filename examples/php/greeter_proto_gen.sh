#!/bin/bash
protoc --proto_path=./../../protos   --php_out=./   --grpc_out=./   --plugin=protoc-gen-grpc=./../../../bins/opt/grpc_php_plugin   ./../../protos/helloworld.proto

