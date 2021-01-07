#! /bin/bash

while [ ! -f /tmp/cfssl/csr_server.json ] || [ ! -f /tmp/cfssl/config_server.json  ]; do 
    echo "no /tmp/cfssl/csr_server.json or /tmp/cfssl/config_server.json detected!"; 
    sleep 10; 
done;

cd /home/cfssl/;
if [ ! -f csr_server.json ] 
then
    cp /tmp/cfssl/csr_server.json /home/cfssl/csr_server.json;
    cfssl genkey -initca=true csr_server.json | cfssljson -bare ca;
fi
if [ ! -f config_server.json ] 
then
    cp /tmp/cfssl/config_server.json /home/cfssl/config_server.json;
fi

cfssl serve -address=0.0.0.0 -ca-key ca-key.pem -ca ca.pem -config config_server.json