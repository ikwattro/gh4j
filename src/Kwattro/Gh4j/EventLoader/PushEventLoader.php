<?php

namespace Kwattro\Gh4j\EventLoader;

class PushEventLoader extends BaseEventLoader
{
	private $baseRepoId;
	private $baseRepoName;
	private $baseRepoOwner;
	private $baseRepoIsFork;
	private $baseRepoLanguage;
	private $pushedBranch;


	public function getEventLoadQuery(array $event)
	{
		$this->prepareCommonEventPayloadQuery($event);
		$this->prepareEventTypePayloadQuery($event);
		
		return $this->buildQuery();
	}

	public function prepareEventTypePayloadQuery($event)
	{
		$repository = $event['repository'];
		$this->baseRepoId = $repository['id'];
		$this->baseRepoName = $repository['name'];
		$this->baseRepoOwner = $repository['owner'];
		$this->baseRepoIsFork = $repository['fork'];
		$this->pushedBranch = $event['payload']['ref'];
		
	}

	public function buildQuery()
	{
		$user= $this->getAlias('u');
		$event = $this->getAlias('ev');
		$fork = $this->getAlias('fork');
		$repo = $this->getAlias('repo');
		$branch = $this->getAlias('branch');
		$owner = $this->getAlias('owner');

		// If repository doesnt exist create it
		$q = 'MERGE (repo_alias:Repository {id:toInt('.$this->baseRepoId.')}) 
		SET repo_alias.name=\''.$this->baseRepoName.'\'';

		// If Branch pushed to doesnt exist, create it
		$q .= 'MERGE (branch_alias:Branch {ref:\''.$this->pushedBranch.'\', repo_id:toInt('.$this->baseRepoId.')}) ';

		// Create relations between Event and Branch && between Branch and Repository
		$q .= 'MERGE (ev_alias)-[:PUSH_TO]->(branch_alias) 
		MERGE (branch_alias)-[:BRANCH_OF]->(repo_alias) ';

		// If the owner of the repository doesnt exist, create it and create the owned by relation
		$q .= 'MERGE (owner_alias:User {name:\''.$this->baseRepoOwner.'\'}) 
		MERGE (repo_alias)-[:OWNED_BY]->(owner_alias) ';

		// If the repository of the pushed branch is also a Fork, we add the Fork label to it
		if ($this->baseRepoIsFork == true) {
			$q .= ' SET repo_alias :Fork';
		}

		$this->addAliases(array('fork', 'repo', 'branch', 'owner'));

		$q = $this->generateAliases($q);

		return $this->getCommonEventPayloadQuery().' '.$q;
	}
}