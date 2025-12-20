<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sqlFile = database_path('sql/procedimientos_compras.sql');
        
        if (file_exists($sqlFile)) {
            DB::unprepared(file_get_contents($sqlFile));
        } else {
            $this->createProcedures();
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_completar_compra');
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_cancelar_compra');
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_registrar_compra_completada');
    }

    private function createProcedures(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_completar_compra");

        DB::unprepared("
            CREATE PROCEDURE sp_completar_compra(
                IN p_idCompra INT,
                IN p_idUsuario INT
            )
            BEGIN
                DECLARE v_idAlmacen INT;
                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    RESIGNAL;
                END;

                START TRANSACTION;

                SELECT idAlmacen INTO v_idAlmacen
                FROM compras
                WHERE idCompra = p_idCompra AND deleted_at IS NULL;

                IF v_idAlmacen IS NULL THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La compra no existe';
                END IF;

                IF NOT EXISTS (SELECT 1 FROM compras WHERE idCompra = p_idCompra AND estado = 'pendiente' AND deleted_at IS NULL) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La compra no está en estado pendiente';
                END IF;

                INSERT INTO stock_almacens (idProducto, idAlmacen, cantidad, costo_unitario, precio_venta, created_at, updated_at)
                SELECT 
                    dc.idProducto AS idProducto,
                    v_idAlmacen AS idAlmacen,
                    dc.cantidad AS cantidad,
                    dc.costo_unitario AS costo_unitario,
                    COALESCE(sa.precio_venta, 0) AS precio_venta,
                    NOW() AS created_at,
                    NOW() AS updated_at
                FROM detalle_compras dc
                LEFT JOIN stock_almacens sa ON sa.idProducto = dc.idProducto 
                    AND sa.idAlmacen = v_idAlmacen 
                    AND sa.deleted_at IS NULL
                WHERE dc.idCompra = p_idCompra AND dc.deleted_at IS NULL
                ON DUPLICATE KEY UPDATE
                    cantidad = stock_almacens.cantidad + VALUES(cantidad),
                    costo_unitario = VALUES(costo_unitario),
                    updated_at = NOW();

                INSERT INTO movimiento_inventarios (idProducto, idAlmacen, tipo, cantidad, costo_unitario, fecha, idUsuario, motivo, created_at, updated_at)
                SELECT 
                    dc.idProducto,
                    v_idAlmacen,
                    'entrada',
                    dc.cantidad,
                    dc.costo_unitario,
                    NOW(),
                    p_idUsuario,
                    CONCAT('Compra completada #', p_idCompra),
                    NOW(),
                    NOW()
                FROM detalle_compras dc
                WHERE dc.idCompra = p_idCompra AND dc.deleted_at IS NULL;

                UPDATE compras
                SET estado = 'completada', 
                    idUsuario = p_idUsuario,
                    updated_at = NOW()
                WHERE idCompra = p_idCompra;

                COMMIT;
            END
        ");

        DB::unprepared("DROP PROCEDURE IF EXISTS sp_cancelar_compra");

        DB::unprepared("
            CREATE PROCEDURE sp_cancelar_compra(
                IN p_idCompra INT,
                IN p_idUsuario INT
            )
            BEGIN
                DECLARE v_idAlmacen INT;
                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    RESIGNAL;
                END;

                START TRANSACTION;

                SELECT idAlmacen INTO v_idAlmacen
                FROM compras
                WHERE idCompra = p_idCompra AND deleted_at IS NULL;

                IF v_idAlmacen IS NULL THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La compra no existe';
                END IF;

                IF NOT EXISTS (SELECT 1 FROM compras WHERE idCompra = p_idCompra AND estado = 'pendiente' AND deleted_at IS NULL) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La compra no está en estado pendiente';
                END IF;

                INSERT INTO movimiento_inventarios (idProducto, idAlmacen, tipo, cantidad, costo_unitario, fecha, idUsuario, motivo, created_at, updated_at)
                SELECT 
                    dc.idProducto,
                    v_idAlmacen,
                    'ajuste',
                    -dc.cantidad,
                    dc.costo_unitario,
                    NOW(),
                    p_idUsuario,
                    CONCAT('Compra cancelada #', p_idCompra),
                    NOW(),
                    NOW()
                FROM detalle_compras dc
                WHERE dc.idCompra = p_idCompra AND dc.deleted_at IS NULL;

                UPDATE compras
                SET estado = 'cancelada', 
                    idUsuario = p_idUsuario,
                    updated_at = NOW()
                WHERE idCompra = p_idCompra;

                COMMIT;
            END
        ");

        DB::unprepared("DROP PROCEDURE IF EXISTS sp_registrar_compra_completada");

        DB::unprepared("
            CREATE PROCEDURE sp_registrar_compra_completada(
                IN p_idCompra INT,
                IN p_idUsuario INT,
                IN p_idAlmacen INT
            )
            BEGIN
                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    RESIGNAL;
                END;

                START TRANSACTION;

                INSERT INTO stock_almacens (idProducto, idAlmacen, cantidad, costo_unitario, precio_venta, created_at, updated_at)
                SELECT 
                    dc.idProducto AS idProducto,
                    p_idAlmacen AS idAlmacen,
                    dc.cantidad AS cantidad,
                    dc.costo_unitario AS costo_unitario,
                    COALESCE(sa.precio_venta, 0) AS precio_venta,
                    NOW() AS created_at,
                    NOW() AS updated_at
                FROM detalle_compras dc
                LEFT JOIN stock_almacens sa ON sa.idProducto = dc.idProducto 
                    AND sa.idAlmacen = p_idAlmacen 
                    AND sa.deleted_at IS NULL
                WHERE dc.idCompra = p_idCompra AND dc.deleted_at IS NULL
                ON DUPLICATE KEY UPDATE
                    cantidad = stock_almacens.cantidad + VALUES(cantidad),
                    costo_unitario = VALUES(costo_unitario),
                    updated_at = NOW();

                INSERT INTO movimiento_inventarios (idProducto, idAlmacen, tipo, cantidad, costo_unitario, fecha, idUsuario, motivo, created_at, updated_at)
                SELECT 
                    dc.idProducto,
                    p_idAlmacen,
                    'entrada',
                    dc.cantidad,
                    dc.costo_unitario,
                    NOW(),
                    p_idUsuario,
                    CONCAT('Compra #', p_idCompra, ' completada'),
                    NOW(),
                    NOW()
                FROM detalle_compras dc
                WHERE dc.idCompra = p_idCompra AND dc.deleted_at IS NULL;

                COMMIT;
            END
        ");
    }
};