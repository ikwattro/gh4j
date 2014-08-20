<?php

namespace Kwattro\Gh4j\Tests;

use Kwark\Kwark;
use Kwattro\Gh4j\Gh4j;

class Gh4jTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaultConnection()
    {
        $gh = new Gh4j();
        $this->assertInstanceOf('Kwattro\Gh4j\Gh4j', $gh);

        $connector = $gh->getConnector();
        $this->assertInstanceOf('Kwark\Client\HttpClientInterface', $connector->getHttpClient());
        $this->assertInstanceOf('Kwark\Client\Api', $connector->getApi());
    }

    public function loadFixturesFile()
    {
        $dir = (__DIR__.'/Fixtures/');
        $filename = 'events.json';

        return file($dir.$filename);
    }

    public function testFileIsLoadedAndParsed()
    {
        $events = $this->loadFixturesFile();
        foreach ($events as $event) {
            $this->assertJson($event);
            break;
        }
    }

    public function testResetDB()
    {
        $reset = $this->resetDB();
        $this->assertTrue($reset->getErrorsCount() === 0);
    }

    public function testLoadPushEvent1()
    {
        $this->resetDB();
        $json = '{"created_at":"2014-06-01T01:05:30-07:00","payload":{"shas":[["d8ab883b3e62db034bf9e28872186e49a641aec0","hawkeyechen@gmail.com","Update footer.html","Hawkeye Chen",true]],"size":1,"ref":"refs/heads/master","head":"d8ab883b3e62db034bf9e28872186e49a641aec0"},"public":true,"type":"PushEvent","url":"https://github.com/hawkeyechen/hawkeyechen.github.io/compare/159f47def7...d8ab883b3e","actor":"hawkeyechen","actor_attributes":{"login":"hawkeyechen","type":"User","gravatar_id":"40587ebfb53adf8db2ff7b0b4992f652","name":"Hawkeye Chen","company":"Student","blog":"http://hawkeyechen.net","location":"","email":"hawkeyechen@gmail.com"},"repository":{"id":19536976,"name":"hawkeyechen.github.io","url":"https://github.com/hawkeyechen/hawkeyechen.github.io","description":"","watchers":0,"stargazers":0,"forks":0,"fork":false,"size":560,"owner":"hawkeyechen","private":false,"open_issues":0,"has_issues":true,"has_downloads":true,"has_wiki":true,"language":"CSS","created_at":"2014-05-07T07:10:07-07:00","pushed_at":"2014-06-01T01:05:30-07:00","master_branch":"master"}}';
        $gh = new Gh4j();
        $gh->loadEvent($json);

        $c = $this->getClient();
        $q = 'MATCH p=(r:Repository {id:19536976, name:\'hawkeyechen.github.io\'})<-[:BRANCH_OF]-(br:Branch {ref:\'refs/heads/master\', repo_id:19536976})
        <-[:PUSH_TO]-(e:PushEvent)<-[:DO]-(u:User {name:\'hawkeyechen\'}) RETURN count(p)';
        $r = $c->sendCypherQuery($q);

        $this->assertTrue($r->getErrorsCount() === 0);
        $this->assertTrue($r->getRawResult()['results'][0]['data'][0]['rest'][0] === 1);

        $this->resetDB();

    }

    public function getClient()
    {
        $c = new Kwark();
        $c->initializeApi();

        return $c;
    }

    public function resetDB()
    {
        $c = $this->getClient();
        $q = 'MATCH (n) OPTIONAL MATCH (n)-[r]-() DELETE r,n';
        $r = $c->sendCypherQuery($q);

        $q = 'MATCH (n) RETURN count(n)';
        $e = $c->sendCypherQuery($q);
        
        return $e;

    }


}