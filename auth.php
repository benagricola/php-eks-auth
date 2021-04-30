<?php
require 'vendor/autoload.php';

use EKS\ClientFactory;
use HTTPMiddleware\DynamicCertificate;
use Maclof\Kubernetes\Exceptions\BadRequestException;

$options = getopt("",["cluster:","region:"]);
$clusterName = $options['cluster'];
$region = $options['region'];
if(!isset($clusterName)){
    echo "No --cluster provided".PHP_EOL;
    exit(127);
}
if(!isset($region)){
    echo "No --region provided".PHP_EOL;
    exit(127);
}

$cf = new ClientFactory();

$k8s = $cf->get($clusterName, $region);

try {

    $namespaces = $k8s->namespaces()->find();

    foreach ($namespaces as $ns) {
        echo "Namespace: " . $ns->getJsonPath('$.metadata.name')[0] . PHP_EOL;
    }

    $pods = $k8s->pods()->find();
    foreach ($pods as $pod) {
        echo "Pod: " . $pod->getJsonPath('$.metadata.name')[0] . PHP_EOL;
    }

} catch (BadRequestException $e) {
    printf("Error retrieving namespaces: %s", $e);
}