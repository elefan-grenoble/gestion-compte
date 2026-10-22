<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\ShiftScenarios;

/**
 * The invitation links of /member/new and /member/add_beneficiary carry an
 * encoded e-mail address (?code=). A malformed code is refused like an
 * expired one (I-BUG-12: it answered 500).
 *
 * @internal
 */
class MemberInviteCodeTest extends FunctionalTestCase
{
    use ShiftScenarios;

    /**
     * @dataProvider malformedCodes
     */
    public function testAMalformedCodeIsRefusedPolitely(string $path, string $code): void
    {
        $client = static::createClient();

        $client->request('GET', $path . '?code=' . urlencode($code));

        $this->assertTrue($client->getResponse()->isRedirect('/'), sprintf('Answered %d.', $client->getResponse()->getStatusCode()));
        $this->assertSame(["Cette url n'est plus valide"], static::flashes($client)['error'] ?? []);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function malformedCodes(): array
    {
        $cases = [];
        foreach (['/member/new', '/member/add_beneficiary'] as $path) {
            $cases[$path . ', not base64'] = [$path, '%%%not-base64%%%'];
            $cases[$path . ', random bytes'] = [$path, base64_encode("\xff\xfe\xfd\x80\x81")];
            $cases[$path . ', plain text'] = [$path, 'hello'];
        }

        return $cases;
    }
}
