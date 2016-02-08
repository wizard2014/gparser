<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use Application\Service\EmailListService;

class IndexController extends AbstractActionController
{
    protected $emailListService;

    public function __construct(EmailListService $emailListService)
    {
        $this->emailListService = $emailListService;
    }

    public function indexAction()
    {
        $request = $this->getRequest();

        if ($request->isXmlHttpRequest()) {
            $data = $request->getPost()->toArray();

            $emails = $this->emailListService->getEmailList($data['email'], $data['password']);

            return new JsonModel([
                'emails' => $emails
            ]);
        }

        return new ViewModel();
    }
}
