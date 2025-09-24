<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class UserRepository
{
    protected User $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    /**
     * Cria um novo usuário
     *
     * @param array $data
     * @return User
     */
    public function create(array $data): User
    {
        try {
            // Criar usuário com timeout otimizado
            return $this->model->create($data);
        } catch (\Exception $e) {
            // Log específico para problemas de criação
            Log::error('Repository: Erro ao criar usuário', [
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data)
            ]);
            throw $e;
        }
    }

    /**
     * Busca usuário por ID
     *
     * @param int $id
     * @return User|null
     */
    public function findById(int $id): ?User
    {
        return $this->model->find($id);
    }

    /**
     * Busca usuário por email
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Busca usuário por CPF
     *
     * @param string $cpf
     * @return User|null
     */
    public function findByCpf(string $cpf): ?User
    {
        return $this->model->where('cpf', $cpf)->first();
    }

    /**
     * Busca usuário por email OU CPF (otimizada com uma query)
     *
     * @param string $email
     * @param string $cpf
     * @return User|null
     */
    public function findByEmailOrCpf(string $email, string $cpf): ?User
    {
        try {
            return $this->model
                ->where(function ($query) use ($email, $cpf) {
                    $query->where('email', $email)
                          ->orWhere('cpf', $cpf);
                })
                ->select(['id', 'email', 'cpf']) // Otimização: buscar apenas campos necessários
                ->first();
        } catch (\Exception $e) {
            Log::error('Repository: Erro ao buscar usuário por email/cpf', [
                'error' => $e->getMessage(),
                'email' => $email,
                'cpf' => $cpf
            ]);
            throw $e;
        }
    }

    /**
     * Verifica se existe usuário com o email
     *
     * @param string $email
     * @return bool
     */
    public function existsByEmail(string $email): bool
    {
        return $this->model->where('email', $email)->exists();
    }

    /**
     * Verifica se existe usuário com o CPF
     *
     * @param string $cpf
     * @return bool
     */
    public function existsByCpf(string $cpf): bool
    {
        return $this->model->where('cpf', $cpf)->exists();
    }

    /**
     * Atualiza usuário
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        return $this->model->where('id', $id)->update($data);
    }

    /**
     * Deleta usuário
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return $this->model->where('id', $id)->delete();
    }

    /**
     * Lista todos os usuários com paginação
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate(int $perPage = 15)
    {
        return $this->model->paginate($perPage);
    }

    /**
     * Busca usuários por filtros
     *
     * @param array $filters
     * @return Collection
     */
    public function findByFilters(array $filters): Collection
    {
        $query = $this->model->newQuery();

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (isset($filters['email'])) {
            $query->where('email', 'like', '%' . $filters['email'] . '%');
        }

        if (isset($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        if (isset($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        return $query->get();
    }

    /**
     * Conta total de usuários
     *
     * @return int
     */
    public function count(): int
    {
        return $this->model->count();
    }
}

