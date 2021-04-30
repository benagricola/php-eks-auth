<?php
namespace HTTPMiddleware;

use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// If request options contain an $optionName ("cacert") value,
// then write the contents to a temporary file and set that as
// the 'verify' option before running the request.
// This allows us to load certificate data on the fly without
// storing it forever on the filesystem.
class DynamicCertificate {
    public static function Handler(string $optionName = 'cacert')
    {
        return function (callable $handler) use ($optionName) {
            return function (RequestInterface $request, array $options) use ($handler, $optionName) {
                // If cert data is given, write it out to a temporary file

                if(isset($options[$optionName])) {
                    $tmpfn = tempnam(sys_get_temp_dir(), 'eks-tmp-ca');
                    file_put_contents($tmpfn, $options[$optionName]);
                    $options['verify'] = $tmpfn;
                }

                // Delete file after request, on both success and error
                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($tmpfn) {
                        unlink($tmpfn);
                        return $response;
                    },
                    function (RequestException $err) use ($tmpfn) {
                        unlink($tmpfn);
                        throw $err;
                    }
                );
            };
        };
    }
}