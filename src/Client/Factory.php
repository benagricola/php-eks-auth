<?php
namespace EKSAuth\Client;

use Aws\EKS\EKSClient;
use Aws\Credentials\CredentialProvider;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Client as HTTPClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;

use EKSAuth\HTTPMiddleware\DynamicCertificate;

class Factory
{
    protected $clusters = [];
    protected $stack;
    protected $awsCredProvider;

    public function __construct() {
        $this->awsCredProvider = CredentialProvider::defaultProvider();
        $this->stack = HandlerStack::create();
        $this->stack->push(DynamicCertificate::Handler());
    }

    protected function lookupCluster(string $name, string $region): array {
        if(!isset($this->clusters[$name])) {
            $eksClient = new EKSClient(['region' => $region, 'version' => '2017-11-01']);
            $cluster = $eksClient->describeCluster(['name' => $name])['cluster'];

            return $this->clusters[$name] = [
                'endpoint' => $cluster['endpoint'],
                'cert' => base64_decode($cluster['certificateAuthority']['data']),
            ];

        } else {
            return $this->clusters[$name];
        }
    }

    protected function generateToken(string $name, string $region): string {
        $signer = new SignatureV4('sts', $region);
        $uri = Uri::withQueryValues(new Uri("https://sts.${region}.amazonaws.com/"), [
            'Action' => 'GetCallerIdentity', 
            'Version' => '2011-06-15'
        ]);
        $psReq = new Request('GET', $uri, [
            'x-k8s-aws-id' => $name,
        ]);
        
        // NOTE: If you set the pre-sign validity too high, the authentication
        // will be rejected! The limit appears to be around 15 minutes.
        $request = $signer->presign($psReq, ($this->awsCredProvider)()->wait(), '+10 minutes');
        return 'k8s-aws-v1.' . str_replace('=','', strtr(base64_encode($request->getUri()), '+/','-_'));
    }

    // Pass an EKS cluster name, region and a function that takes a
    // single argument $httpClient and returns a new instance of your desired
    // Kubernetes client library.
    public function getClient(string $clusterName, string $region, callable $clientFunc)
    {
        $cluster = $this->lookupCluster($clusterName, $region);
        $token = $this->generateToken($clusterName, $region);
        
        $httpClient = new HTTPClient([
            'base_uri' => $cluster['endpoint'], 
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'handler' => $this->stack,
            'cacert' => $cluster['cert'],
        ]);
        return $clientFunc($httpClient);
    }
}
