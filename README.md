# PHP EKS Auth

This library uses the AWS V3 SDK to create an authenticated `GuzzleHttp\Client` instance that can be passed to your compatible PHP Kubernetes client (only tested with `maclof/kubernetes-client`). 

All you need to authenticate with an EKS cluster is valid AWS credentials in your environment. 

This library will pull the EKS endpoint details from AWS based on `$clusterName` and `$region`, using the default credential provider from `aws-sdk-php`.

The `GuzzleHttp\Client` instance will be preconfigured with a `DynamicCertificate` Middleware that writes the CA certificate of the cluster to a temporary file so it can be passed to the underlying HTTP Handler (usually Curl). 

The temporary certificate file is created and deleted on every request so does not need to be cleaned up, and means connections are fully verified.

## Usage

```php
use EKSAuth\Client\Factory as ClientFactory;

# Example using maclof/kubernetes-client
use Maclof\Kubernetes\Client;

// Create a new ClientFactory.
// EKS Cluster details are cached for the
// lifetime of this Factory instance.
$cf = new ClientFactory();

// Get our client. A new Token will be generated every
// time getClient() is called.

// We pass our own function that instantiates a new 
// Maclof\Kubernetes\Client instance with the
// pre-configured \GuzzleHttp\Client.
$k8s = $cf->getClient($clusterName, $region, function($httpClient) {
    return new Client([], $httpClient);
});

$namespaces = $k8s->namespaces()->find();
...
```

## Contributing
Submit a pull request. I'm not a PHP dev so the codebase has no tests 