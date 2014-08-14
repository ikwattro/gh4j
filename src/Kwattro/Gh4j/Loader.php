<?php

namespace Kwattro\Gh4j;

use Kwattro\Gh4j\EventLoader\PullRequestEventLoader;
use Kwattro\Gh4j\EventLoader\ForkEventLoader;
use Kwattro\Gh4j\EventLoader\IssueCommentEventLoader;
use Kwattro\Gh4j\EventLoader\PushEventLoader;

class Loader
{
	private $handledEvents = array(
		'PullRequestEvent',
		'ForkEvent',
		'PushEvent',
		'IssueCommentEvent'
		);

	private $loadedEventLoaders = array();

	public function isHandled($eventType)
	{
		return in_array($eventType, $this->getHandledEvents());
	}

	public function getHandledEvents()
	{
		return $this->handledEvents;
	}

	public function getEventLoader($event)
	{
		$type = $event['type'];

		if ($this->isHandled($type)) {
			return $loader = $this->getLoader($type);
		}
		return false;
	}

	private function getLoader($eventType)
	{
		switch ($eventType) {
			case 'PullRequestEvent':
				if (!array_key_exists($eventType, $this->loadedEventLoaders)) {
					$loader = new PullRequestEventLoader();
					$this->loadedEventLoaders[$eventType] = $loader;
					return $loader;
				}
				return $this->loadedEventLoaders[$eventType];
				break;
			case 'ForkEvent':
			return new ForkEventLoader();
				if (!array_key_exists($eventType, $this->loadedEventLoaders)) {
					$loader = new ForkEventLoader();
					$this->loadedEventLoaders[$eventType] = $loader;
					return $loader;
				}
				return $this->loadedEventLoaders[$eventType];
				break;
			case 'PushEvent':
				if (!array_key_exists($eventType, $this->loadedEventLoaders)) {
					$loader = new PushEventLoader();
					$this->loadedEventLoaders[$eventType] = $loader;
					return $loader;
				}
				return $this->loadedEventLoaders[$eventType];
				break;
			case 'IssueCommentEvent':
				if (!array_key_exists($eventType, $this->loadedEventLoaders)) {
					$loader = new IssueCommentEventLoader();
					$this->loadedEventLoaders[$eventType] = $loader;
					return $loader;
				}
				return $this->loadedEventLoaders[$eventType];
				break;
		}
	}
}