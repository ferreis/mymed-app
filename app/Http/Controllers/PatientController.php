<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Receita;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::user()->id;

        $queryPacientes = Paciente::with('user', 'lembretes', 'receitas')->where('usuario', $userId);
        $queryReceitas = Receita::with(['medico.user', 'paciente.user'])->whereHas('paciente', function ($query) use ($userId) {
            $query->where('usuario', $userId);
        });

        if ($request->filled('filter')) {
            $filter = $request->input('filter');

            $queryPacientes->where(function ($query) use ($filter) {
                $query->where('identifier', $filter)
                      ->orWhereHas('user', function ($q) use ($filter) {
                          $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($filter) . '%'])
                            ->orWhereRaw('LOWER(rg) LIKE ?', ['%' . strtolower($filter) . '%'])
                            ->orWhereRaw('LOWER(cpf) LIKE ?', ['%' . strtolower($filter) . '%'])
                            ->orWhereRaw('LOWER(endereco) LIKE ?', ['%' . strtolower($filter) . '%'])
                            ->orWhereRaw('LOWER(telefone) LIKE ?', ['%' . strtolower($filter) . '%']);
                      });
            });

            $queryReceitas->where(function ($query) use ($filter) {
                $query->where('codigoUnico', $filter)
                      ->orWhereRaw('LOWER(tipoEspecial) LIKE ?', ['%' . strtolower($filter) . '%'])
                      ->orWhereHas('paciente.user', function ($q) use ($filter) {
                          $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($filter) . '%']);
                      })
                      ->orWhereHas('medico.user', function ($q) use ($filter) {
                          $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($filter) . '%']);
                      });
            });
        }

        $pacientes = $queryPacientes->get();
        $receitas = $queryReceitas->get();

        return view('patient.index', compact('pacientes', 'receitas'));
    }


    public function create()
    {
        return view('patient.create');
    }

    public function store(Request $request)
    {
        // Verificação se o CPF já existe
        if (User::where('cpf', $request->cpf)->exists()) {
            return redirect()->back()->withErrors(['cpf' => 'Este CPF já está cadastrado.'])->withInput();
        }
        // Verificação se o CPF já existe
        if (User::where('email', $request->email)->exists()) {
            return redirect()->back()->withErrors(['email' => 'Este CPF já está cadastrado.'])->withInput();
        }

        // Gerar username único
        $firstName = explode(' ', trim($request->name))[0];
        $username = $this->generateUniqueUsername($firstName);

        // Gerar senha aleatória
        $password = $this->generateRandomPassword();

        // Criação do usuário
        $user = new User;
        $user->name = $request->name;
        $user->username = $username;
        $user->email = $request->email;
        $user->telefone = $request->telefone;
        $user->cpf = $request->cpf;
        $user->rg = $request->rg;
        $user->endereco = $request->endereco;
        $user->password = Hash::make($password);
        $user->save();


        // Criação do paciente
        $paciente = Paciente::create([
            'usuario' => $user->id,
        ]);

        return redirect()->route('patient.index')->with('success', 'Paciente cadastrado com sucesso. Senha gerada: ' . $password);
    }

    private function generateUniqueUsername($firstName)
    {
        do {
            $randomNumber = rand(10000, 99999);
            $username = $firstName . $randomNumber;
        } while (User::where('username', $username)->exists());

        return $username;
    }

    private function generateRandomPassword()
    {
        $letters = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 3);
        $numbers = substr(str_shuffle('0123456789'), 0, 3);
        return str_shuffle($letters . $numbers);
    }
}