<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use Illuminate\Database\Schema\Blueprint;
use LogicException;

/** Disposable schema and synthetic fixtures; never imports the patrimonial SQL dump. */
final class TestDatabase
{
    public static function isolate(Application $app): void
    {
        $app['config']->set('database.default', 'sqlite');
        // Remove every configured real connection, including any cached URL/credentials.
        $app['config']->set('database.connections', ['sqlite' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
    }

    public static function prepare(Application $app): void
    {
        if (! $app->runningUnitTests()) {
            throw new LogicException('TestDatabase only runs in the testing environment.');
        }
        $manager = $app['db'];
        // Reject even an explicitly reconfigured server driver in a test.
        foreach (['mysql', 'pgsql', 'sqlsrv'] as $driver) {
            $manager->extend($driver, static function () {
                throw new LogicException('Server database connections are forbidden in tests.');
            });
        }
        $manager->extend('sqlite', static function (array $config, string $name) use ($app) {
            if (($config['database'] ?? null) !== ':memory:' || ! empty($config['url'])) {
                throw new LogicException('Only SQLite :memory: connections are permitted in tests.');
            }
            return $app['db.factory']->make($config, $name);
        });
        $db = $manager->connection();
        if ($db->getDriverName() !== 'sqlite' || $db->getDatabaseName() !== ':memory:') {
            throw new LogicException('Refusing to prepare a database other than SQLite :memory:.');
        }
        $schema = $db->getSchemaBuilder();
        foreach (['roles' => 50, 'especialidades' => 100, 'sedes' => 100, 'tipos_ambiente' => 50,
            'estados_bien' => 50, 'condiciones_bien' => 50, 'marcas' => 80,
            'tipos_equipo' => 100, 'tipos_mueble' => 100, 'tipos_mantenimiento' => 50] as $table => $length) {
            $schema->create($table, function (Blueprint $t) use ($table, $length): void {
                $t->increments('id'); $t->string('nombre', $length)->unique();
                if (in_array($table, ['roles', 'estados_bien', 'condiciones_bien', 'tipos_mantenimiento'], true)) $t->string('descripcion', 150)->nullable();
                if ($table === 'sedes') { $t->string('direccion', 200)->nullable(); $t->boolean('activo')->default(true); }
            });
        }
        $schema->create('usuarios', function (Blueprint $t): void {
            $t->increments('id'); $t->string('nombres', 100); $t->string('apellidos', 100);
            $t->string('correo', 120)->unique(); $t->string('password_hash');
            $t->unsignedInteger('rol_id'); $t->unsignedInteger('especialidad_id')->nullable();
            $t->boolean('activo')->default(true); $t->dateTime('fecha_registro')->nullable();
            $t->foreign('rol_id')->references('id')->on('roles');
            $t->foreign('especialidad_id')->references('id')->on('especialidades');
        });
        $schema->create('ambientes', function (Blueprint $t): void {
            $t->increments('id'); $t->string('nombre', 100); $t->unsignedInteger('sede_id');
            $t->unsignedInteger('tipo_ambiente_id')->nullable(); $t->unsignedInteger('especialidad_id')->nullable();
            foreach (['area', 'ancho', 'largo'] as $field) $t->decimal($field, 10, 2)->nullable();
            $t->boolean('activo')->default(true);
            $t->foreign('sede_id')->references('id')->on('sedes');
            $t->foreign('tipo_ambiente_id')->references('id')->on('tipos_ambiente');
            $t->foreign('especialidad_id')->references('id')->on('especialidades');
        });
        $schema->create('bienes', function (Blueprint $t): void {
            $t->increments('id'); $t->string('cbi', 25)->nullable()->unique(); $t->string('descripcion', 150);
            foreach (['estado_id' => 'estados_bien', 'condicion_id' => 'condiciones_bien', 'ambiente_id' => 'ambientes'] as $field => $target) {
                $t->unsignedInteger($field)->nullable(); $t->foreign($field)->references('id')->on($target);
            }
            $t->string('observaciones')->nullable(); $t->boolean('activo')->default(true); $t->dateTime('fecha_registro')->nullable();
        });
        $schema->create('equipos', function (Blueprint $t): void {
            $t->unsignedInteger('bien_id')->primary(); $t->unsignedInteger('tipo_equipo_id'); $t->unsignedInteger('marca_id')->nullable();
            $t->string('modelo', 100)->nullable(); $t->string('serie', 100)->nullable();
            $t->foreign('bien_id')->references('id')->on('bienes');
            $t->foreign('tipo_equipo_id')->references('id')->on('tipos_equipo');
            $t->foreign('marca_id')->references('id')->on('marcas');
        });
        $schema->create('equipos_computo', function (Blueprint $t): void {
            $t->unsignedInteger('bien_id')->primary();
            foreach (['procesador' => 100, 'ram' => 100, 'almacenamiento' => 150, 'detalle_adicional' => 255] as $field => $length) $t->string($field, $length)->nullable();
            $t->foreign('bien_id')->references('bien_id')->on('equipos');
        });
        $schema->create('monitores', function (Blueprint $t): void {
            $t->unsignedInteger('bien_id')->primary(); $t->string('tamanio_pantalla', 50)->nullable(); $t->string('detalle_pantalla', 150)->nullable();
            $t->foreign('bien_id')->references('bien_id')->on('equipos');
        });
        $schema->create('muebles', function (Blueprint $t): void {
            $t->unsignedInteger('bien_id')->primary(); $t->unsignedInteger('tipo_mueble_id');
            $t->string('material', 150)->nullable(); $t->string('color', 80)->nullable();
            $t->foreign('bien_id')->references('id')->on('bienes'); $t->foreign('tipo_mueble_id')->references('id')->on('tipos_mueble');
        });
        $schema->create('inventarios', function (Blueprint $t): void {
            $t->unsignedInteger('bien_id'); $t->integer('anio'); $t->string('inventario', 20);
            $t->primary(['bien_id', 'anio']); $t->foreign('bien_id')->references('id')->on('bienes');
        });
        $schema->create('mantenimientos', function (Blueprint $t): void {
            $t->increments('id'); $t->unsignedInteger('bien_id'); $t->unsignedInteger('tipo_mantenimiento_id'); $t->unsignedInteger('tecnico_id')->nullable();
            $t->date('fecha_mantenimiento'); $t->string('descripcion');
            foreach (['diagnostico', 'trabajo_realizado', 'observaciones'] as $field) $t->string($field)->nullable();
            $t->foreign('bien_id')->references('id')->on('bienes'); $t->foreign('tipo_mantenimiento_id')->references('id')->on('tipos_mantenimiento');
            $t->foreign('tecnico_id')->references('id')->on('usuarios');
        });
        $schema->create('movimientos', function (Blueprint $t): void {
            $t->increments('id'); $t->unsignedInteger('bien_id');
            $t->unsignedInteger('ambiente_origen_id')->nullable(); $t->unsignedInteger('ambiente_destino_id');
            $t->unsignedInteger('ordenado_por'); $t->unsignedInteger('ejecutado_por');
            $t->dateTime('fecha_movimiento'); $t->string('motivo'); $t->string('observaciones')->nullable();
            $t->enum('estado', ['pendiente_firma', 'finalizado'])->default('pendiente_firma');
            $t->string('pdf_generado_ruta')->nullable(); $t->string('pdf_firmado_ruta')->nullable(); $t->dateTime('fecha_firma')->nullable();
            $t->foreign('bien_id')->references('id')->on('bienes');
            foreach (['ambiente_origen_id', 'ambiente_destino_id'] as $field) $t->foreign($field)->references('id')->on('ambientes');
            foreach (['ordenado_por', 'ejecutado_por'] as $field) $t->foreign($field)->references('id')->on('usuarios');
        });
        $schema->create('estaciones_pc', function (Blueprint $t): void {
            $t->increments('id'); $t->string('codigo', 20)->unique(); $t->string('descripcion', 100)->nullable(); $t->boolean('activo')->default(true);
        });
        $schema->create('estacion_componentes', function (Blueprint $t): void {
            $t->increments('id'); $t->unsignedInteger('estacion_id'); $t->unsignedInteger('bien_id');
            $t->date('fecha_asignacion')->nullable(); $t->date('fecha_retiro')->nullable(); $t->boolean('activo')->default(true);
            $t->foreign('estacion_id')->references('id')->on('estaciones_pc'); $t->foreign('bien_id')->references('id')->on('bienes');
        });
        self::fixtures($db);
    }

    private static function fixtures(\Illuminate\Database\Connection $db): void
    {
        foreach (['superadmin', 'administrador', 'director', 'coordinador', 'asistente'] as $i => $role) $db->table('roles')->insert(['id' => $i + 1, 'nombre' => $role]);
        foreach (['Carrera A', 'Carrera B', 'Carrera C', 'Administrativo', 'General'] as $i => $name) $db->table('especialidades')->insert(['id' => $i + 1, 'nombre' => $name]);
        $db->table('sedes')->insert(['id' => 1, 'nombre' => 'Sede simulada']);
        $db->table('tipos_ambiente')->insert([['id' => 1, 'nombre' => 'LABORATORIO'], ['id' => 2, 'nombre' => 'OFICINA']]);
        foreach ([1, 2] as $id) $db->table('ambientes')->insert(['id' => $id, 'nombre' => "Ambiente simulado $id", 'sede_id' => 1, 'tipo_ambiente_id' => $id, 'especialidad_id' => $id]);
        $db->table('estados_bien')->insert(['id' => 1, 'nombre' => 'REGISTRADO']);
        foreach (['OPERATIVO', 'AUSENTE', 'INOPERATIVO'] as $i => $name) $db->table('condiciones_bien')->insert(['id' => $i + 1, 'nombre' => $name]);
        $db->table('tipos_equipo')->insert(['id' => 1, 'nombre' => 'CPU']);
        $db->table('tipos_mueble')->insert(['id' => 1, 'nombre' => 'SILLA']);
        $db->table('marcas')->insert(['id' => 1, 'nombre' => 'Marca simulada']);
        // One active superadmin is a deliberate fixture for last-superadmin tests.
        $db->table('usuarios')->insert(['id' => 1, 'nombres' => 'Usuario', 'apellidos' => 'Simulado', 'correo' => 'fixture@example.test',
            'password_hash' => password_hash('Only-a-test-fixture!', PASSWORD_BCRYPT, ['cost' => 4]), 'rol_id' => 1, 'activo' => true]);
        foreach ([1, 2] as $id) {
            $db->table('bienes')->insert(['id' => $id, 'cbi' => "000000$id", 'descripcion' => "Bien simulado $id", 'ambiente_id' => $id, 'estado_id' => 1, 'condicion_id' => 1]);
            $db->table('inventarios')->insert(['bien_id' => $id, 'anio' => 2026, 'inventario' => "INV-$id"]);
        }
        $db->table('tipos_mantenimiento')->insert(['id' => 1, 'nombre' => 'Mantenimiento simulado']);
    }
}
