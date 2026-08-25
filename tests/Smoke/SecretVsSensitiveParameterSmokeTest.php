<?php /** @noinspection PhpToStringImplementationInspection */

/** @noinspection PhpExpressionResultUnusedInspection */

/** @noinspection PhpUnreachableStatementInspection */

/** @noinspection PsalmAdvanceCallableParamsInspection */

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Smoke;

use Northrook\Container\Secret;
use Northrook\Context;
use Northrook\Context\AppEnv;
use Northrook\Context\ContextManager;
use Northrook\Contracts\Serializable;
use Northrook\Contracts\Tests\Support\SecretMask;
use Northrook\Kernel\KernelContext;
use Northrook\Parameter\Secret as SecretPolicy;
use Northrook\RuntimeException;
use Northrook\Serializer;
use Northrook\Singleton;
use Northrook\Snapshot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarExporter\Exception\NotInstantiableTypeException;
use Symfony\Component\VarExporter\VarExporter;

/**
 * Exploratory smoke: native {@see \SensitiveParameter} vs package
 * {@see Secret} / {@see Serializer} across PHP-out channels.
 *
 * Asserts observed engine + package behaviour (not an aspirational matrix).
 * Classification comments: blocked | engine-limit | standard.
 *
 * @internal
 */
#[Group('smoke')]
final class SecretVsSensitiveParameterSmokeTest extends TestCase
{
    public const string MARKER = 'SMOKE_SECRET_MARKER_hunter2';

    private ContextManager $contextManager;

    protected function setUp(): void
    {
        $this->resetIsolation();

        $this->contextManager = new ContextManager;
        Context::register(
            appEnv        : AppEnv::Testing,
            contextManager: $this->contextManager,
        );
    }

    protected function tearDown(): void
    {
        $this->resetIsolation();
    }

    // ─── helpers ───────────────────────────────────────────────────────────

    /**
     * @param callable(): mixed $callback
     */
    private static function capture(
        callable $callback,
    ): string {
        \ob_start();
        try {
            $callback();
        }
        catch (\Throwable $e) {
            echo \get_class($e) . ': ' . $e->getMessage();
        }

        return (string) \ob_get_clean();
    }

    private static function leaks(
        string $haystack,
        string $needle = self::MARKER,
    ): bool {
        return \str_contains($haystack, $needle);
    }

    private function becomeOutbound(): void
    {
        $this->contextManager->update(KernelContext::Request);
    }

    private function resetIsolation(): void
    {
        $property = new \ReflectionProperty(Singleton::class, '_instance');
        $property->setValue(null, []);

        $property = new \ReflectionProperty(ContextManager::class, 'initialized');
        $property->setValue(null, false);
    }

    // ─── Native: SensitiveParameter attribute on stored properties ─────────

    public function testNativePromotedSensitiveParameterLeaksInVarDump(): void
    {
        // engine-limit: attribute never wraps stored property values
        $user = new NativePromotedSensitive(self::MARKER);
        $out  = self::capture(static fn() => \var_dump($user));

        self::assertTrue(self::leaks($out), 'var_dump leaks plain promoted SensitiveParameter props');
    }

    public function testNativePromotedSensitiveParameterLeaksInPrintR(): void
    {
        $user = new NativePromotedSensitive(self::MARKER);
        $out  = self::capture(static fn() => \print_r($user));

        self::assertTrue(self::leaks($out));
    }

    public function testNativePromotedSensitiveParameterLeaksInVarExport(): void
    {
        $user = new NativePromotedSensitive(self::MARKER);
        $out  = self::capture(static fn() => \var_export($user));

        self::assertTrue(self::leaks($out));
    }

    public function testNativePromotedSensitiveParameterLeaksInJsonEncode(): void
    {
        $user = new NativePromotedSensitive(self::MARKER);

        self::assertTrue(self::leaks((string) \json_encode($user)));
    }

    public function testNativePromotedSensitiveParameterSerializesPlaintext(): void
    {
        $user = new NativePromotedSensitive(self::MARKER);

        self::assertTrue(self::leaks(\serialize($user)));
    }

    public function testNativePromotedSensitiveParameterLeaksViaGetObjectVars(): void
    {
        $user = new NativePromotedSensitive(self::MARKER);

        self::assertSame(self::MARKER, \get_object_vars($user)['password']);
    }

    public function testNativeCustomDebugInfoDoesNotAutoRedact(): void
    {
        // engine-limit: returning the property from __debugInfo still prints plaintext
        $user = new NativeDebugInfoSensitive(self::MARKER);
        $out  = self::capture(static fn() => \var_dump($user));

        self::assertTrue(self::leaks($out));
    }

    // ─── Native: SensitiveParameterValue wrapper ───────────────────────────

    public function testSensitiveParameterValueHidesInVarDump(): void
    {
        // blocked-by-native (wrapper only)
        $spv = new \SensitiveParameterValue(self::MARKER);
        $out = self::capture(static fn() => \var_dump($spv));

        self::assertFalse(self::leaks($out));
        self::assertStringContainsString('SensitiveParameterValue', $out);
    }

    public function testSensitiveParameterValueHidesInPrintR(): void
    {
        $spv = new \SensitiveParameterValue(self::MARKER);
        $out = \print_r($spv, true);

        self::assertFalse(self::leaks($out));
    }

    public function testSensitiveParameterValueJsonEncodesEmptyObject(): void
    {
        $spv  = new \SensitiveParameterValue(self::MARKER);
        $json = \json_encode($spv);

        self::assertSame('{}', $json);
        self::assertFalse(self::leaks((string) $json));
    }

    public function testSensitiveParameterValueRefusesSerialize(): void
    {
        $spv = new \SensitiveParameterValue(self::MARKER);

        $this->expectException(\Exception::class);
        \serialize($spv);
    }

    public function testSensitiveParameterValueRefusesStringCast(): void
    {
        $spv = new \SensitiveParameterValue(self::MARKER);

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line
        (string) $spv;
    }

    public function testSensitiveParameterValueGetValueReturnsSecret(): void
    {
        // engine-limit / intentional: getValue() is the escape hatch
        $spv = new \SensitiveParameterValue(self::MARKER);

        self::assertSame(self::MARKER, $spv->getValue());
    }

    public function testSensitiveParameterValueLeaksViaSprintfGetValue(): void
    {
        $spv   = new \SensitiveParameterValue(self::MARKER);
        $value = $spv->getValue();
        self::assertIsString($value);
        $out = \sprintf('pw=%s', $value);

        self::assertTrue(self::leaks($out));
    }

    // ─── Native: stack traces / backtrace ──────────────────────────────────

    public function testExceptionTraceWrapsSensitiveArgsWhenIgnoreArgsOff(): void
    {
        $previous = \ini_get('zend.exception_ignore_args');
        \ini_set('zend.exception_ignore_args', '0');

        try {
            $throw = static function(
                string $user,
                #[\SensitiveParameter]
                string $password,
            ): never {
                throw new \RuntimeException("fail {$user}");
            };

            try {
                $throw('ada', self::MARKER);
            }
            catch (\Throwable $e) {
                $args = $e->getTrace()[0]['args'] ?? [];
                self::assertCount(2, $args);
                self::assertSame('ada', $args[0]);
                self::assertInstanceOf(\SensitiveParameterValue::class, $args[1]);
                self::assertSame(self::MARKER, $args[1]->getValue());

                $asString = (string) $e;
                self::assertFalse(
                    self::leaks($asString),
                    'stringified throwable must not print sensitive arg plaintext',
                );
                self::assertStringContainsString('SensitiveParameterValue', $asString);
            }
        }
        finally {
            \ini_set('zend.exception_ignore_args', (string) $previous);
        }
    }

    public function testExceptionTraceOmitsArgsWhenIgnoreArgsOn(): void
    {
        // engine-limit / ini: default often hides ALL args — SensitiveParameter never fires
        $previous = \ini_get('zend.exception_ignore_args');
        \ini_set('zend.exception_ignore_args', '1');

        try {
            $throw = static function(
                string $user,
                #[\SensitiveParameter]
                string $password,
            ): never {
                throw new \RuntimeException("fail {$user}");
            };

            try {
                $throw('ada', self::MARKER);
            }
            catch (\Throwable $e) {
                self::assertArrayNotHasKey('args', $e->getTrace()[0] ?? []);
                self::assertFalse(self::leaks((string) $e));
            }
        }
        finally {
            \ini_set('zend.exception_ignore_args', (string) $previous);
        }
    }

    public function testDebugBacktraceWrapsSensitiveArgs(): void
    {
        $previous = \ini_get('zend.exception_ignore_args');
        // debug_backtrace args are independent of exception_ignore_args, but keep parity
        \ini_set('zend.exception_ignore_args', '0');

        try {
            $probe = static function(
                #[\SensitiveParameter]
                string $password,
            ): array {
                return \debug_backtrace()[0]['args'] ?? [];
            };

            $args = $probe(self::MARKER);
            self::assertInstanceOf(\SensitiveParameterValue::class, $args[0] ?? null);
            self::assertSame(self::MARKER, $args[0]->getValue());
        }
        finally {
            \ini_set('zend.exception_ignore_args', (string) $previous);
        }
    }

    public function testDebugPrintBacktraceIgnoreArgsStillSafe(): void
    {
        $out = self::capture(static function(): void {
            $probe = static function(
                #[\SensitiveParameter]
                string $password,
            ): void {
                \debug_print_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
            };
            $probe(self::MARKER);
        });

        self::assertFalse(self::leaks($out));
    }

    public function testExceptionMessageInterpolationLeaksBeforeAttributeMatters(): void
    {
        // standard: never interpolate secrets into messages — attribute only wraps args
        $throw = static function(
            #[\SensitiveParameter]
            string $password,
        ): void {
            throw new \RuntimeException("bad password {$password}");
        };

        $thrown = false;
        try {
            $throw(self::MARKER);
        }
        catch (\Throwable $e) {
            $thrown = true;
            self::assertTrue(self::leaks($e->getMessage()));
            self::assertTrue(self::leaks((string) $e));
        }

        self::assertTrue($thrown, 'expected throw');
    }

    // ─── Native: arrays / closures / non-promoted ──────────────────────────

    public function testArraysHaveNoSensitiveParameterSurface(): void
    {
        // engine-limit
        $data = ['password' => self::MARKER];
        self::assertTrue(self::leaks(self::capture(static fn() => \var_dump($data))));
        self::assertTrue(self::leaks((string) \json_encode($data)));
        self::assertTrue(self::leaks(\serialize($data)));
    }

    public function testArrayWithSpvSiblingStillLeaksPlainLeaves(): void
    {
        $sibling = 'VISIBLE_SIBLING_TOKEN_xyz';
        $data    = [
            'password' => new \SensitiveParameterValue(self::MARKER),
            'token'    => $sibling,
        ];
        $out = self::capture(static fn() => \var_dump($data));

        self::assertFalse(self::leaks($out, self::MARKER), 'SPV leaf hidden');
        self::assertTrue(self::leaks($out, $sibling), 'plain sibling still prints');
    }

    public function testNonPromotedPropertyAttributeDoesNotExistOnProperty(): void
    {
        // SensitiveParameter targets PARAMETER only — cannot annotate bare properties
        $ref = new \ReflectionProperty(NativeAssignedAfterConstruct::class, 'password');
        self::assertSame([], $ref->getAttributes(\SensitiveParameter::class));

        $obj           = new NativeAssignedAfterConstruct;
        $obj->password = self::MARKER;
        self::assertTrue(self::leaks(self::capture(static fn() => \var_dump($obj))));
    }

    public function testClosureSensitiveParameterWrapsInBacktrace(): void
    {
        $fn = static function(
            #[\SensitiveParameter]
            string $password,
        ): array {
            return \debug_backtrace()[0]['args'] ?? [];
        };

        $args = $fn(self::MARKER);
        self::assertInstanceOf(\SensitiveParameterValue::class, $args[0] ?? null);
    }

    public function testVariadicSensitiveParameterWrapsEachArg(): void
    {
        $fn = static function(
            #[\SensitiveParameter]
            string ...$secrets,
        ): array {
            return \debug_backtrace()[0]['args'] ?? [];
        };

        $args = $fn(self::MARKER, self::MARKER . '_b');
        // PHP expands variadic args in backtrace (not one packed array)
        self::assertCount(2, $args);
        self::assertInstanceOf(\SensitiveParameterValue::class, $args[0]);
        self::assertInstanceOf(\SensitiveParameterValue::class, $args[1]);
        self::assertSame(self::MARKER, $args[0]->getValue());
        self::assertSame(self::MARKER . '_b', $args[1]->getValue());
    }

    public function testPromotedSensitiveParameterAttributeLivesOnlyOnParameter(): void
    {
        // engine detail: TARGET_PARAMETER — ReflectionProperty sees nothing; Serializer still
        // resolves via PropertyAttributes constructor-parameter lookup.
        $property  = new \ReflectionProperty(NativePromotedSensitive::class, 'password');
        $parameter = new \ReflectionClass(NativePromotedSensitive::class)->getConstructor()?->getParameters()[0];

        self::assertSame([], $property->getAttributes(\SensitiveParameter::class));
        self::assertNotNull($parameter);
        self::assertCount(1, $parameter->getAttributes(\SensitiveParameter::class));

        $obj = new NativePromotedSensitive(self::MARKER);
        // Without Serializer: still leaks. With PropertyAttributes path (Snapshot): redacts.
        self::assertTrue(self::leaks(self::capture(static fn() => \var_dump($obj))));
        self::assertFalse(self::leaks(\var_export(Snapshot::value($obj), true)));
    }

    public function testSensitiveArrayParameterWrapsEntireArray(): void
    {
        // native: whole array becomes one SPV; getValue() returns plaintext structure
        $previous = \ini_get('zend.exception_ignore_args');
        \ini_set('zend.exception_ignore_args', '0');

        try {
            $throw = static function(
                #[\SensitiveParameter]
                array $claims,
            ): never {
                throw new \RuntimeException('x');
            };

            try {
                $throw(['token' => self::MARKER]);
            }
            catch (\Throwable $e) {
                $arg = $e->getTrace()[0]['args'][0] ?? null;
                self::assertInstanceOf(\SensitiveParameterValue::class, $arg);
                self::assertSame(['token' => self::MARKER], $arg->getValue());
                self::assertFalse(self::leaks((string) $e));
            }
        }
        finally {
            \ini_set('zend.exception_ignore_args', (string) $previous);
        }
    }

    public function testMethodParameterAttributeDoesNotProtectStoredProperty(): void
    {
        // standard: attribute protects the call frame, not subsequent storage
        $obj = new MethodParamThenStore;
        $obj->set(self::MARKER);

        self::assertTrue(self::leaks(self::capture(static fn() => \var_dump($obj))));
        self::assertSame(self::MARKER, $obj->password);
    }

    public function testNamedArgumentSensitiveStillWraps(): void
    {
        $fn = static function(
            string $user,
            #[\SensitiveParameter]
            string $password,
        ): array {
            return \debug_backtrace()[0]['args'] ?? [];
        };

        $args = $fn(
            password: self::MARKER,
            user    : 'ada',
        );
        // Order follows declaration, not call order
        self::assertSame('ada', $args[0]);
        self::assertInstanceOf(\SensitiveParameterValue::class, $args[1]);
    }

    // ─── Package: #[Secret] alone (no Serializer) ──────────────────────────

    public function testPackageAttributeAloneLeaksInNativeDumps(): void
    {
        // standard: attribute without Serializer / Snapshot is metadata only
        $obj = new AttributeOnlySecret(self::MARKER);
        self::assertTrue(self::leaks(self::capture(static fn() => \var_dump($obj))));
        self::assertTrue(self::leaks((string) \json_encode($obj)));
        self::assertTrue(self::leaks(\serialize($obj)));
    }

    // ─── Package: Serializer + #[Secret] ───────────────────────────────────

    public function testSerializerDebugRedactsSensitiveAndCredential(): void
    {
        $obj  = SerializedDual::make();
        $info = $obj->__debugInfo();

        self::assertSame('ada', $info['username']);
        self::assertSame(SecretMask::sensitive(self::MARKER), $info['password']);
        self::assertSame('[secret::credential]', $info['dsn']);
        self::assertFalse(self::leaks(\var_export($info, true)));
    }

    public function testSerializerVarDumpHonoursDebugInfo(): void
    {
        $obj = SerializedDual::make();
        $out = self::capture(static fn() => \var_dump($obj));

        self::assertFalse(self::leaks($out));
        self::assertStringContainsString(SecretMask::sensitive(self::MARKER), $out);
        self::assertStringContainsString('[secret::credential]', $out);
    }

    public function testSerializerPrintRHonoursDebugInfo(): void
    {
        $obj = SerializedDual::make();
        $out = \print_r($obj, true);

        self::assertFalse(self::leaks($out));
    }

    public function testSerializerVarExportBypassesRedaction(): void
    {
        // engine-limit: var_export does not call __debugInfo
        $obj = SerializedDual::make();
        $out = self::capture(static fn() => \var_export($obj));

        self::assertTrue(self::leaks($out));
    }

    public function testSerializerGetObjectVarsBypassesRedaction(): void
    {
        // engine-limit / standard: raw property access
        $obj = SerializedDual::make();

        self::assertSame(self::MARKER, \get_object_vars($obj)['password']);
        self::assertSame('postgres://' . self::MARKER, \get_object_vars($obj)['dsn']);
    }

    public function testSerializerArrayCastBypassesRedaction(): void
    {
        $obj = SerializedDual::make();
        $arr = (array) $obj;

        self::assertTrue(self::leaks(\var_export($arr, true)));
    }

    public function testSerializerSerializeAllowsBothTiersOutsideHttpContext(): void
    {
        $sensitiveOnly = SerializedSensitiveOnly::make();
        self::assertTrue(self::leaks(\serialize($sensitiveOnly)), 'sensitive may leave via serialize');

        $dual = SerializedDual::make();
        self::assertTrue(self::leaks(\serialize($dual)), 'credential may leave when trusted');
    }

    public function testSerializerSerializeThrowsCredentialInHttpContext(): void
    {
        $this->becomeOutbound();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $dsn');
        \serialize(SerializedDual::make());
    }

    public function testSerializerJsonAllowsSensitiveThrowsCredentialInHttpContext(): void
    {
        $sensitiveOnly = SerializedSensitiveOnly::make();
        self::assertTrue(self::leaks((string) \json_encode($sensitiveOnly)));

        $this->becomeOutbound();

        $this->expectException(RuntimeException::class);
        \json_encode(SerializedDual::make(), \JSON_THROW_ON_ERROR);
    }

    public function testSerializerDirectPropertyReadLeaks(): void
    {
        // engine-limit: public props are plaintext at runtime (by design for use site)
        $obj = SerializedDual::make();
        self::assertSame(self::MARKER, $obj->password);
    }

    // ─── Package: #[SensitiveParameter] via PropertyAttributes ─────────────

    public function testSerializerMapsNativeSensitiveParameterToSensitiveTier(): void
    {
        $obj  = SerializedNativeSensitive::make();
        $info = $obj->__debugInfo();

        self::assertSame(
            '[secret::' . \SensitiveParameter::class . ']',
            $info['password'],
        );
        self::assertSame(self::MARKER, $obj->__serialize()['password'], 'SP ≈ sensitive: serialize OK');
    }

    public function testSerializerBothAttributesCredentialWinsRegardlessOfOrder(): void
    {
        // #[Secret(CREDENTIAL)] on property + #[SensitiveParameter] on param — merge, not first-match
        $credentialFirst = SerializedBothAttributesSecretFirst::make();
        $info            = $credentialFirst->__debugInfo();

        self::assertSame('[secret::credential]', $info['token']);
        self::assertSame(self::MARKER, $credentialFirst->__serialize()['token'], 'credential OK when trusted');

        // Reverse declaration order: SP on property site via promoted + Secret(CREDENTIAL) still wins
        $sensitiveFirst = SerializedBothAttributesSensitiveFirst::make();
        self::assertSame(
            '[secret::credential]',
            $sensitiveFirst->__debugInfo()['token'],
        );

        $this->becomeOutbound();
        $this->expectException(RuntimeException::class);
        $credentialFirst->__serialize();
    }

    // ─── Package: Snapshot (reflective walker) ─────────────────────────────

    public function testSnapshotRedactsAttributedSecretsWithoutSerializer(): void
    {
        // blocked-by-package: Snapshot uses PropertyAttributes, not the trait
        $obj  = new AttributeOnlySecret(self::MARKER);
        $snap = Snapshot::value($obj);

        $export = \var_export($snap, true);
        self::assertFalse(self::leaks($export), 'Snapshot must redact #[Secret] without Serializer');
    }

    public function testSnapshotRedactsNativeSensitiveParameter(): void
    {
        $obj    = new NativePromotedSensitive(self::MARKER);
        $snap   = Snapshot::value($obj);
        $export = \var_export($snap, true);

        self::assertFalse(self::leaks($export));
    }

    public function testSnapshotCannotRedactPlainArraySecrets(): void
    {
        // engine-limit: no attribute surface on array keys
        $snap = Snapshot::value(['password' => self::MARKER]);
        self::assertTrue(self::leaks(\var_export($snap, true)));
    }

    // ─── Package: inheritance / nesting / uninitialized ────────────────────

    public function testNestedSerializableRedactsIndependently(): void
    {
        $outer = new NestedOuter(SerializedSensitiveOnly::make());
        $info  = $outer->__debugInfo();

        // Nested object left as object in debugInfo — var_dump recurses into nested __debugInfo
        $out = self::capture(static fn() => \var_dump($outer));
        self::assertFalse(self::leaks($out));
        unset($info);
    }

    public function testParentPrivateSecretIsRedacted(): void
    {
        $obj  = ChildWithParentSecret::make();
        $info = $obj->__debugInfo();

        self::assertSame(SecretMask::sensitive(self::MARKER), $info['parentSecret']);
        self::assertSame('visible', $info['childPlain']);
        self::assertSame(self::MARKER, $obj->parentSecret());
    }

    public function testUninitializedSecretPropertyShowsPlaceholder(): void
    {
        $obj = new class implements Serializable {
            use Serializer;

            #[Secret]
            public string $token;
        };

        self::assertSame('[uninitialized]', $obj->__debugInfo()['token']);
        self::assertArrayNotHasKey('token', $obj->__serialize());
    }

    public function testNonPromotedSecretOnPropertyResolved(): void
    {
        $obj           = new NonPromotedSecretProperty;
        $obj->password = self::MARKER;

        self::assertSame(SecretMask::sensitive(self::MARKER), $obj->__debugInfo()['password']);
        self::assertSame(self::MARKER, $obj->__serialize()['password']);
    }

    public function testSecretOnConstructorParameterOnly(): void
    {
        $obj = new ParamOnlySecret(self::MARKER);

        self::assertSame(SecretMask::sensitive(self::MARKER), $obj->__debugInfo()['password']);
    }

    // ─── Reflection / debug_zval / export footguns ─────────────────────────

    public function testReflectionPropertyGetValueBypassesEverything(): void
    {
        // engine-limit: absolute
        $obj = SerializedDual::make();
        $ref = new \ReflectionProperty($obj, 'password');

        self::assertSame(self::MARKER, $ref->getValue($obj));
    }

    public function testDebugZvalDumpHonoursDebugInfo(): void
    {
        // PHP 8.5: debug_zval_dump uses __debugInfo when present (same as var_dump)
        $obj = SerializedDual::make();
        $out = self::capture(static fn() => \debug_zval_dump($obj));

        self::assertFalse(self::leaks($out));
        self::assertStringContainsString(SecretMask::sensitive(self::MARKER), $out);
    }

    public function testDebugZvalDumpLeaksWithoutDebugInfo(): void
    {
        // engine-limit: without __debugInfo, zval dump prints raw properties
        $obj = new AttributeOnlySecret(self::MARKER);
        $out = self::capture(static fn() => \debug_zval_dump($obj));

        self::assertTrue(self::leaks($out));
    }

    public function testStringInterpolationOfPublicSecretLeaks(): void
    {
        $obj = SerializedDual::make();
        $out = "password={$obj->password}";

        self::assertTrue(self::leaks($out));
    }

    public function testClonePreservesPlaintextAndPolicy(): void
    {
        $obj   = SerializedSensitiveOnly::make();
        $clone = clone $obj;

        self::assertSame(self::MARKER, $clone->password);
        self::assertSame(SecretMask::sensitive(self::MARKER), $clone->__debugInfo()['password']);
    }

    // ─── Symfony VarExporter (export / persist channel) ────────────────────

    public function testVarExporterPassesThroughSensitivePlaintext(): void
    {
        // expected by tier policy: sensitive may leave the process intentionally
        $code = VarExporter::export(SerializedSensitiveOnly::make());

        self::assertTrue(self::leaks($code));
        self::assertStringContainsString('states', $code, 'uses __serialize state path');
    }

    public function testVarExporterPassesThroughCredentialOutsideHttpContext(): void
    {
        $code = VarExporter::export(SerializedDual::make());

        self::assertTrue(self::leaks($code), 'trusted persist may carry credential');
    }

    public function testVarExporterThrowsOnCredentialInHttpContext(): void
    {
        $this->becomeOutbound();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot serialize credential property $dsn');

        VarExporter::export(SerializedDual::make());
    }

    public function testVarExporterLeaksNativeSensitiveParameterProperties(): void
    {
        // engine-limit: no __serialize — property walk, attribute ignored
        $code = VarExporter::export(new NativePromotedSensitive(self::MARKER));

        self::assertTrue(self::leaks($code));
        self::assertStringContainsString('properties', $code);
    }

    public function testVarExporterLeaksSecretAttributeWithoutSerializer(): void
    {
        // standard: #[Secret] alone is metadata
        $code = VarExporter::export(new AttributeOnlySecret(self::MARKER));

        self::assertTrue(self::leaks($code));
    }

    public function testVarExporterMapsNativeSpViaSerializerAsSensitive(): void
    {
        // Package maps SP → sensitive tier → plaintext export allowed
        $code = VarExporter::export(SerializedNativeSensitive::make());

        self::assertTrue(self::leaks($code));
    }

    public function testVarExporterRejectsSensitiveParameterValue(): void
    {
        // native SPV is not instantiable for export (≠ PHP serialize's message)
        $this->expectException(NotInstantiableTypeException::class);
        $this->expectExceptionMessage('SensitiveParameterValue');

        VarExporter::export(new \SensitiveParameterValue(self::MARKER));
    }

    public function testVarExporterRoundTripRestoresSensitiveAndKeepsDebugRedaction(): void
    {
        $code = VarExporter::export(SerializedSensitiveOnly::make());
        /** @var SerializedSensitiveOnly $restored */
        $restored = eval('return ' . $code . ';');

        self::assertInstanceOf(SerializedSensitiveOnly::class, $restored);
        self::assertSame(self::MARKER, $restored->password);
        self::assertSame(SecretMask::sensitive(self::MARKER), $restored->__debugInfo()['password']);
    }

    public function testVarExporterNestedSerializerStillExportsSensitiveInner(): void
    {
        $outer = new NestedOuter(SerializedSensitiveOnly::make());
        $code  = VarExporter::export($outer);

        self::assertTrue(self::leaks($code));
    }

    // ─── Matrix provider: compact channel × subject leak table ─────────────

    /**
     * @return iterable<string, array{callable(): object, string, bool}>
     */
    public static function dumpChannelMatrix(): iterable
    {
        yield 'native-SP var_dump' => [
            static fn() => new NativePromotedSensitive(self::MARKER),
            'var_dump',
            true, // leaks
        ];
        yield 'serializer var_dump' => [
            static fn() => SerializedSensitiveOnly::make(),
            'var_dump',
            false,
        ];
        yield 'serializer var_export' => [
            static fn() => SerializedSensitiveOnly::make(),
            'var_export',
            true,
        ];
        yield 'attribute-only var_dump' => [
            static fn() => new AttributeOnlySecret(self::MARKER),
            'var_dump',
            true,
        ];
        yield 'SPV var_dump' => [
            static fn() => new class(new \SensitiveParameterValue(self::MARKER)) {
                public function __construct(
                    public \SensitiveParameterValue $password,
                ) {}
            },
            'var_dump',
            false,
        ];
    }

    #[DataProvider('dumpChannelMatrix')]
    public function testDumpChannelMatrix(
        callable $factory,
        string   $channel,
        bool     $expectLeak,
    ): void {
        $subject = $factory();
        $out     = match ($channel) {
            'var_dump'   => self::capture(static fn() => \var_dump($subject)),
            'var_export' => self::capture(static fn() => \var_export($subject)),
            default      => self::fail("unknown channel {$channel}"),
        };

        self::assertSame($expectLeak, self::leaks($out), "{$channel} leak expectation");
    }
}

// ─── fixtures ──────────────────────────────────────────────────────────────

final readonly class NativePromotedSensitive
{
    public function __construct(
        #[\SensitiveParameter]
        public string $password,
    ) {}
}

final readonly class NativeDebugInfoSensitive
{
    public function __construct(
        #[\SensitiveParameter]
        public string $password,
    ) {}

    public function __debugInfo(): array
    {
        return ['password' => $this->password];
    }
}

final class NativeAssignedAfterConstruct
{
    public string $password;
}

final readonly class AttributeOnlySecret
{
    public function __construct(
        #[Secret]
        public string $password,
    ) {}
}

final class SerializedDual implements Serializable
{
    use Serializer;

    public function __construct(
        public string $username,
        #[Secret]
        public string $password,
        #[Secret(SecretPolicy::CREDENTIAL)]
        public string $dsn,
    ) {}

    public static function make(): self
    {
        return new self('ada', SecretVsSensitiveParameterSmokeTest::MARKER, 'postgres://' . SecretVsSensitiveParameterSmokeTest::MARKER);
    }
}

final class SerializedSensitiveOnly implements Serializable
{
    use Serializer;

    public function __construct(
        #[Secret]
        public string $password,
    ) {}

    public static function make(): self
    {
        return new self(SecretVsSensitiveParameterSmokeTest::MARKER);
    }
}

final class SerializedNativeSensitive implements Serializable
{
    use Serializer;

    public function __construct(
        #[\SensitiveParameter]
        public string $password,
    ) {}

    public static function make(): self
    {
        return new self(SecretVsSensitiveParameterSmokeTest::MARKER);
    }
}

final class SerializedBothAttributesSecretFirst implements Serializable
{
    use Serializer;

    #[Secret(SecretPolicy::CREDENTIAL)]
    public string $token;

    public function __construct(
        #[\SensitiveParameter]
        string $token,
    ) {
        $this->token = $token;
    }

    public static function make(): self
    {
        return new self(SecretVsSensitiveParameterSmokeTest::MARKER);
    }
}

/**
 * Both attrs on the constructor parameter only; SP declared first so resolve order
 * would have preferred SENSITIVE under first-match — merge must still yield CREDENTIAL.
 */
final class SerializedBothAttributesSensitiveFirst implements Serializable
{
    use Serializer;

    public string $token;

    public function __construct(
        #[\SensitiveParameter]
        #[Secret(SecretPolicy::CREDENTIAL)]
        string $token,
    ) {
        $this->token = $token;
    }

    public static function make(): self
    {
        return new self(SecretVsSensitiveParameterSmokeTest::MARKER);
    }
}

final class NestedOuter implements Serializable
{
    use Serializer;

    public function __construct(
        public SerializedSensitiveOnly $inner,
    ) {}
}

class ParentWithSecret implements Serializable
{
    use Serializer;

    public function __construct(
        #[Secret]
        private string $parentSecret,
    ) {}

    public function parentSecret(): string
    {
        return $this->parentSecret;
    }
}

final class ChildWithParentSecret extends ParentWithSecret
{
    public function __construct(
        string        $parentSecret,
        public string $childPlain,
    ) {
        parent::__construct($parentSecret);
    }

    public static function make(): self
    {
        return new self(SecretVsSensitiveParameterSmokeTest::MARKER, 'visible');
    }
}

final class NonPromotedSecretProperty implements Serializable
{
    use Serializer;

    #[Secret]
    public string $password;
}

final class ParamOnlySecret implements Serializable
{
    use Serializer;

    public string $password;

    public function __construct(
        #[Secret]
        string $password,
    ) {
        $this->password = $password;
    }
}

final class MethodParamThenStore
{
    public string $password;

    public function set(
        #[\SensitiveParameter]
        string $password,
    ): void {
        $this->password = $password;
    }
}
