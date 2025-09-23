<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cpf', 11)->nullable()->unique()->after('email');
            $table->string('phone', 15)->after('cpf');
            $table->date('birthdate')->nullable()->after('phone');
            $table->enum('gender', ['M', 'F', 'Outro'])->nullable()->after('birthdate');
            $table->boolean('accept_terms')->default(false)->after('gender');

            // Endereço
            $table->string('street', 255)->after('accept_terms');
            $table->string('number', 10)->after('street');
            $table->string('complement', 100)->nullable()->after('number');
            $table->string('neighborhood', 100)->after('complement');
            $table->string('city', 100)->after('neighborhood');
            $table->char('state', 2)->after('city');
            $table->char('zip_code', 8)->after('state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['cpf']);
            $table->dropColumn([
                'cpf', 'phone', 'birthdate', 'gender', 'accept_terms',
                'street', 'number', 'complement', 'neighborhood',
                'city', 'state', 'zip_code'
            ]);
        });
    }
};
