<?php

namespace OCA\ChurchToolsIntegration\Listeners;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;

/**
 * @template-implements IEventListener<NodeCreatedEvent|NodeWrittenEvent>
 */
class NodeListener implements IEventListener {
	public function handle(Event $event): void {
		if ($event instanceof NodeCreatedEvent) {

		} else {
			// elseif ($event instanceof NodeWrittenEvent) {
			$this->handleWritten($event);
		}
	}

	private function handleWritten(NodeWrittenEvent $event): void {
		var_dump($event->getNode());
	}
}
