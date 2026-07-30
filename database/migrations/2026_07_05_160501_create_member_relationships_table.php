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
        Schema::create('member_relationships', function (Blueprint $table): void {
            $table->id();
            // Pemilik relasi & anggota yang dituju. Baris kebalikannya (timbal-balik)
            // dibuat otomatis oleh model MemberRelationship.
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('related_member_id')->constrained('members')->cascadeOnDelete();
            $table->enum('relationship_type', ['spouse', 'child', 'parent', 'grandchild', 'grandparent']);
            $table->timestamps();

            // Satu pasang anggota hanya boleh punya satu jenis relasi langsung.
            $table->unique(['member_id', 'related_member_id']);
            $table->index('related_member_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_relationships');
    }
};
