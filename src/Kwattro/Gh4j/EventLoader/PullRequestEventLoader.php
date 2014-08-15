<?php

namespace Kwattro\Gh4j\EventLoader;

class PullRequestEventLoader extends BaseEventLoader
{
	private $prAction;
	private $openedBy;
	private $openedAt;
	private $prNumber;
	private $id;
	private $originalRepoId;
	private $originalRepoName;
	private $originalRepoOwner;
	private $merged;
	private $pullRequest;
	private $pullRequestState;
	private $comingRef;
	private $repoId;
	private $repoName;
	private $repoIsFork;
	private $baseRepoId;
	private $baseRepoName;
	private $fromRepoOwner;

	public function getEventLoadQuery(array $event)
	{
		$this->prepareCommonEventPayloadQuery($event);
		$this->prepareEventTypePayloadQuery($event);

		return $this->buildQuery();
	}

	public function prepareEventTypePayloadQuery($event)
	{


		$this->prAction = $event['payload']['action'];
		$this->openedAt = new \DateTime($event['payload']['pull_request']['created_at']);
		$this->openedBy = $event['payload']['pull_request']['user']['login'];
		$this->id = $event['payload']['pull_request']['id'];
		$this->prNumber = $event['payload']['number'];
		$this->originalRepoId = $event['payload']['pull_request']['base']['repo']['id'];
		$this->originalRepoOwner = $event['payload']['pull_request']['base']['repo']['owner']['login'];
		$this->originalRepoName = $event['payload']['pull_request']['base']['repo']['name'];
		$this->merged = $event['payload']['pull_request']['merged'];
		$this->pullRequest = $event['payload']['pull_request'];
		$ref = $event['payload']['pull_request']['head']['ref'];
		$longref = 'refs/heads/'.$ref;
		$this->comingRef = $longref;
		$this->repoId = $event['payload']['pull_request']['head']['repo']['id'];
		$this->repoName = $event['payload']['pull_request']['head']['repo']['name'];
		$this->repoIsFork = $event['payload']['pull_request']['head']['repo']['fork'];
		$this->baseRepoId = $event['payload']['pull_request']['base']['repo']['id'];
		$this->baseRepoName = $event['payload']['pull_request']['base']['repo']['name'];
		$this->fromRepoOwner = $event['payload']['pull_request']['head']['repo']['owner']['login'];

		$state = $this->pullRequest['state'];
		if ($state == 'closed' && $this->merged == true) {
			$this->pullRequestState = 'merged';
		} else {
			$this->pullRequestState = $state;
		}
	}

	public function buildQuery()
	{

		// Create the Pull Request Node
		// Update 1 : First we have to lookup for htmlUrl property in case the PR was guessed from a IssueCommentEvent based on a PR
		// 
		// Old Query - lookup based on id
		//$q = 'MERGE (pr:PullRequest {id:toInt('.$this->id.'), number:toInt('.$this->prNumber.'), html_url:\''.$this->pullRequest['html_url'].'\'})
		//SET pr.state = \''.$this->pullRequestState.'\'';

		$q = 'MERGE (pr_alias:PullRequest {html_url:\''.$this->pullRequest['html_url'].'\'}) 
		SET pr_alias += { id:toInt('.$this->id.'), number:toInt('.$this->prNumber.'), state:\''.$this->pullRequestState.'\'} ';

		// If a new PR is opened :
		if ($this->prAction == 'opened') {
			$q .= 'MERGE (ev)-[:PR_OPEN]->(pr_alias)';
		}

		// If a PR is closed but not merged
		if ($this->prAction == 'closed' && false === $this->merged) {
			$q .= 'MERGE (ev)-[:PR_CLOSE]->(pr_alias)';
		}

		// If a PR is closed and merged
		if ($this->prAction == 'closed' && true === $this->merged) {
			$q .= 'MERGE (ev)-[:PR_CLOSE]->(pr_alias)
			MERGE (ev)-[:PR_MERGE]->(pr_alias)';
		}

		// If it is not a PR opening action, we can guess by whom and when the PR was opened
		// and then create the PR_OPEN Event and Relation to the PR
		if ($this->prAction != 'opened') {
			$opener = $this->pullRequest['user']['login'];
			$openingTime = new \DateTime($this->pullRequest['created_at']);

			$q .= 'MERGE (pr_alias)<-[:PR_OPEN]-(pr_opener_alias:PullRequestEvent)
			ON CREATE SET pr_opener_alias.time = toInt('.$openingTime->getTimestamp().') 
			WITH pr_alias, pr_opener_alias, u';
			$q .= ' MATCH (pr_alias)<-[:PR_OPEN]-(pr_opening_event_alias:PullRequestEvent) 
			MERGE (opener_alias:User {name:\''.$opener.'\'}) 
			MERGE (pr_opening_event_alias)<-[:DO]-(opener_alias)';
		}

		// Setting Original Repo Informations
		$q .= 'MERGE (original_repo_owner_alias:User {name:\''.$this->originalRepoOwner.'\'})
		MERGE (original_repo_alias:Repository {id:toInt('.$this->originalRepoId.'), name:\''.$this->originalRepoName.'\'})
		MERGE (original_repo_alias)-[:OWNED_BY]->(original_repo_owner_alias)
		MERGE (pr_alias)-[:PR_ON_REPO]->(original_repo_alias)';

		// Matching/Creating from which Fork and Branch the PR is made
		$q .= 'MERGE (branch:Branch {ref:\''.$this->comingRef.'\', repo_id:toInt('.$this->repoId.')}) 
		MERGE (fromRepo:Repository {id:toInt('.$this->repoId.')}) 
		ON CREATE SET fromRepo.name = \''.$this->repoName.'\'
		WITH branch, fromRepo, u, pr_alias
		MERGE (branch)-[:BRANCH_OF]->(fromRepo)
		MERGE (fromRepoOwner:User{name:\''.$this->fromRepoOwner.'\'})
		MERGE (fromRepo)-[:OWNED_BY]->(fromRepoOwner) 
		MERGE (pr_alias)-[:FROM_BRANCH]->(branch)';

		if ($this->repoIsFork) {
			$q .= 'SET fromRepo :Fork 
			MERGE (forkOf:Repository {id:toInt('.$this->baseRepoId.')}) 
			ON CREATE SET forkOf.name = \''.$this->baseRepoName.'\'
			WITH fromRepo, forkOf
			MERGE (fromRepo)-[:FORK_OF]->(forkOf)';
		}

		return $this->getCommonEventPayloadQuery().' '.$q;
	}
}