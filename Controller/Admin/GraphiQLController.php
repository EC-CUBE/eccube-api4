<?php

namespace Plugin\Api\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Controller\AbstractController;
use League\OAuth2\Server\CryptTrait;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Trikoder\Bundle\OAuth2Bundle\Manager\AuthorizationCodeManagerInterface;
use Trikoder\Bundle\OAuth2Bundle\Manager\ClientManagerInterface;
use Trikoder\Bundle\OAuth2Bundle\Manager\Doctrine\AuthorizationCodeManager;
use Trikoder\Bundle\OAuth2Bundle\Model\AuthorizationCode;

class GraphiQLController extends AbstractController
{
    use CryptTrait;

    /** @var AuthorizationCodeManagerInterface */
    private $authzCodeManager;
    /** @var ClientManagerInterface */
    private $clientManager;
    

    public function __construct(
        AuthorizationCodeManagerInterface $authzCodeManager,
        ClientManagerInterface $clientManager
    )
    {
        $this->authzCodeManager = $authzCodeManager;
        $this->clientManager = $clientManager;
    }

    /**
     * @Route("/%eccube_admin_route%/graphiql", name="admin_api_graphiql", methods={"GET"})
     * @Template("@Api/admin/OAuth/graphiql.twig")
     *
     * @return array|RedirectResponse
     */
    public function graphiql()
    {
        if ($this->session->has('token')) {
            return [
                'access_token' => $this->session->get('token')['access_token']
            ];
        }

        return $this->redirectToRoute('admin_api_config');
    }
    /**
     * @Route("/%eccube_admin_route%/callback", name="admin_api_callback", methods={"GET", "POST"})
     * //@Template("@Api/admin/OAuth/edit.twig")
     * @param Request $request
     *
     * @return Response
     */
    public function callback(Request $request)
    {
        $code = $request->query->get('code');
        $this->setEncryptionKey(env('ECCUBE_OAUTH2_ENCRYPTION_KEY'));
        $authCodePayload = json_decode($this->decrypt($code));
        $Client = $this->clientManager->find($authCodePayload->client_id);
        $httpClient = new \GuzzleHttp\Client();
        $params = [
            'form_params' =>
            [
                'code'          => $code,
                'client_id'     => $Client->getIdentifier(),
                'client_secret' => $Client->getSecret(),
                'redirect_uri'  => $this->generateUrl('admin_api_callback', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'grant_type'    => 'authorization_code'
            ],
            'headers' => [
                'encoding' => 'application/x-www-form-urlencoded'
            ]
        ];

        if ($code) {
            $res = $httpClient->post($this->generateUrl('oauth2_token', [], UrlGeneratorInterface::ABSOLUTE_URL), $params);
            $token = json_decode($res->getBody()->getContents(), true);
            $this->session->set('token', $token);

            return $this->redirectToRoute('admin_api_graphiql');
        }

        throw new BadRequestHttpException();
    }
}
