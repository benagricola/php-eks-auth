<?php
require __DIR__.'/../vendor/autoload.php';

// NOTE: The library itself does not require maclof/kubernetes-client
// as this may not be the library chosen by the user. To get this
// script to work, you will need to run:
//
// composer require maclof/kubernetes-client

// Any Kubernetes client can be used, as long as it can 
// accept a \GuzzleHttp\Client instance.
use EKSAuth\Client\Factory as ClientFactory;
use Maclof\Kubernetes\Client;

$options = getopt("",["cluster:","region:"]);
$clusterName = $options['cluster'];
$region = $options['region'];

function ll($msg, ...$params) {
    echo vsprintf($msg . PHP_EOL, $params);
}

if(!isset($clusterName)){
    ll("No --cluster provided");
    exit(127);
}
if(!isset($region)){
    ll("No --region provided");
    exit(127);
}

// Get our new ClientFactory. Cluster details will be
// cached for the existence of this instance.
$cf = new ClientFactory();

// Get our client. A new Token will be generated every
// time this is called.
// We pass our own function that instantiates a new 
// Maclof\Kubernetes\Client instance with the
// pre-configured \GuzzleHttp\Client.
try {
    $k8s = $cf->getClient($clusterName, $region, function($httpClient) {
        return new Client([], $httpClient);
    });
} catch(Exception $e) {
    ll($e->getMessage());
    exit(126);
}


// Use the new client to list namespaces and the pods in them.
try {
    $namespaces = $k8s->namespaces()->find();
    foreach ($namespaces as $ns) {
        $nsn = $ns->getJsonPath('$.metadata.name')[0];
        ll("Namespace: %s", $nsn);
        
        $k8s->setNamespace($nsn);
        $pods = $k8s->pods()->find();
        foreach ($pods as $pod) {
            $pn = $pod->getJsonPath('$.metadata.name')[0];
            ll("Pod: %s", $pn);
        }
    }
} catch (Exception $e) {
    ll("Error retrieving namespaces or pods %s", $e);
}