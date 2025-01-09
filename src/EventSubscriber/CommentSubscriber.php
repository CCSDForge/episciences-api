<?php

namespace App\EventSubscriber;

use ApiPlatform\Symfony\EventListener\EventPriorities;
use App\Entity\Paper;
use App\Entity\PaperComment;
use App\Entity\Review;
use App\Traits\ToolsTrait;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class CommentSubscriber implements EventSubscriberInterface
{

    use ToolsTrait;

    public function __construct(private EntityManagerInterface $entityManager, private LoggerInterface $logger, private ParameterBagInterface $parameters)
    {
    }

    public function processComment(ViewEvent $event): void
    {
        $object = $event->getControllerResult();

        if (!$object instanceof Paper && !$object instanceof PaperComment) {
            return;
        }

        if ($object instanceof Paper) {
            try {
                $comments = $object->getComments()->getIterator();

                foreach ($comments as $comment) {
                    $comment->setTypeLabel($comment::TYPE_LABEL[$comment->getType()]);

                    if ($file = $comment->getFile()) {

                        $rvCode = $this->entityManager->getRepository(Review::class)->findOneBy(['rvid' => $object->getRvid()])?->getCode();

                        $filesPath = $this->processFilePath($comment, $rvCode);
                        $arrayFilesPath = [];

                        if ($this->isJson($file)) {
                            $arrayFiles = json_decode($file, true, 512, JSON_THROW_ON_ERROR);
                            foreach ($arrayFiles as $cFile) {
                                $arrayFilesPath [] = $filesPath . $cFile;
                            }
                        } else {
                            $arrayFilesPath [] = $filesPath . $file;
                        }

                        $comment->setFileContent($this->base64Files($arrayFilesPath));
                    }

                }


            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }

        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['processComment', EventPriorities::PRE_SERIALIZE],];
    }


    private function processFilePath(PaperComment $comment, string $rvCode): string
    {

        if (!$comment->getFile()) {
            return '';
        }

        $filesPath = sprintf('%s%s/files/%s/', $this->parameters->get('app.files.path'), $rvCode, $comment->getDocid());
        $filesPath .= $comment->isCopyEditingComment() ? sprintf('copy_editing_sources/%s/', $comment->getPcid()) : sprintf('/%s/', 'comments');
        return $filesPath;

    }


    private function base64Files(array $filesPath = []): array
    {

        $base64Files = [];

        foreach ($filesPath as $filePath) {
            $base64Files[] = $this->toBase64($filePath);
        }

        return $base64Files;

    }

}
