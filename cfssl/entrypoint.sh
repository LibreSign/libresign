#! /bin/bash

if [ ! -f csr_server.json ] || [ ! -f config_server.json  ]; then
    echo "no csr_server.json or config_server.json detected!";
fi;
while [ ! -f csr_server.json ] || [ ! -f config_server.json  ]; do
    sleep 1;
done;

if [ ! -f ca-key.pem ]
then
    cfssl genkey -initca=true csr_server.json | cfssljson -bare ca;
fi

cfssl serve -address=0.0.0.0 -ca-key ca-key.pem -ca ca.pem -config config_server.json