<?php

namespace OCA\ChurchToolsIntegration\Listeners;

use OCA\ChurchToolsIntegration\Jobs\UpdatePerson;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\PostLoginEvent;

/**
 * @template-implements IEventListener<PostLoginEvent>
 */
class LoginListener implements IEventListener {
	public function handle(Event $event): void {
		UpdatePerson::dispatch($event->getUser());
	}
}
