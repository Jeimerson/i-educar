<?php

require_once 'lib/Portabilis/Controller/ApiCoreController.php';
require_once 'lib/Portabilis/Array/Utils.php';
require_once 'lib/Portabilis/String/Utils.php';
require_once 'lib/Portabilis/Utils/Database.php';
require_once 'include/modules/clsModulesComponenteCurricularAnoEscolar.inc.php';
require_once 'include/pmieducar/clsPmieducarEscolaSerieDisciplina.inc.php';
require_once 'ComponenteCurricular/Model/TurmaDataMapper.php';

class ComponentesSerieController extends ApiCoreController
{
    public function atualizaComponentesDaSerie()
    {
        $serieId = $this->getRequest()->serie_id;
        $componentes = json_decode($this->getRequest()->componentes);
        $arrayComponentes = [];

        foreach ($componentes as $key => $componente) {
            $arrayComponentes[$key]['id'] = $componente->id;
            $arrayComponentes[$key]['carga_horaria'] = $componente->carga_horaria;
            $arrayComponentes[$key]['tipo_nota'] = $componente->tipo_nota;
            $arrayComponentes[$key]['anos_letivos'] = $componente->anos_letivos;
        }

        $obj = new clsModulesComponenteCurricularAnoEscolar(null, $serieId, null, null, $arrayComponentes);

        $updateInfo = $obj->updateInfo();
        $componentesAtualizados = $updateInfo['update'];
        $componentesInseridos = $updateInfo['insert'];
        $componentesExcluidos = $updateInfo['delete'];

        if ($obj->atualizaComponentesDaSerie()) {
            if ($componentesExcluidos) {
                $this->atualizaExclusoesDeComponentes($serieId, $componentesExcluidos);
            }

            return [
                'update' => $componentesAtualizados,
                'insert' => $componentesInseridos,
                'delete' => $componentesExcluidos
            ];
        }

        return ['msgErro' => 'Erro ao alterar componentes da série.'];
    }

    public function atualizaEscolasSerieDisciplina()
    {
        $serieId = $this->getRequest()->serie_id;
        $componentes = json_decode($this->getRequest()->componentes);
        $arrayComponentes = [];

        foreach ($componentes as $key => $componente) {
            $arrayComponentes[$key]['id'] = $componente->id;
            $arrayComponentes[$key]['carga_horaria'] = $componente->carga_horaria;
        }

        $this->replicaComponentesAdicionadosNasEscolas($serieId, $arrayComponentes);
    }

    public function replicaComponentesAdicionadosNasEscolas($serieId, $componentes)
    {
        $escolas = $this->getEscolasDaSerie($serieId);

        if ($escolas && $componentes) {
            foreach ($escolas as $escola) {
                foreach ($componentes as $componente) {
                    $objEscolaSerieDisciplina = new clsPmieducarEscolaSerieDisciplina($serieId, $escola['ref_cod_escola'], $componente['id']);

                    if (!$objEscolaSerieDisciplina->cadastra()) {
                        return false;
                    }
                }
            }
        }
    }

    public function getUltimoAnoLetivoAberto()
    {
        $objEscolaAnoLetivo = new clsPmieducarEscolaAnoLetivo();
        $ultimoAnoLetivoAberto = $objEscolaAnoLetivo->getUltimoAnoLetivoAberto();

        return $ultimoAnoLetivoAberto;
    }

    public function getEscolasDaSerie($serieId)
    {
        $objEscolaSerie = new clsPmieducarEscolaSerie();
        $escolasDaSerie = $objEscolaSerie->lista(null, $serieId);

        if ($escolasDaSerie) {
            return $escolasDaSerie;
        }

        return false;
    }

    public function getTurmasDaSerieNoAnoLetivoAtual($serieId)
    {
        $objTurmas = new clsPmieducarTurma();
        $turmasDaSerie = $objTurmas->lista(null, null, null, $serieId, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, $this->getUltimoAnoLetivoAberto());

        if ($turmasDaSerie) {
            return $turmasDaSerie;
        }

        return false;
    }

    public function excluiEscolaSerieDisciplina($escolaId, $serieId, $disciplinaId)
    {
        $objEscolaSerieDisiciplina = new clsPmieducarEscolaSerieDisciplina($serieId, $escolaId, $disciplinaId);

        if ($objEscolaSerieDisiciplina->excluir()) {
            return true;
        }

        return false;
    }

    public function excluiComponenteDaTurma($componenteId, $turmaId)
    {
        $mapper = new ComponenteCurricular_Model_TurmaDataMapper();
        $where = ['componente_curricular_id' => $componenteId, 'turma_id' => $turmaId];
        $componente = $mapper->findAll(['componente_curricular_id', 'turma_id'], $where, [], false);

        if ($componente && $mapper->delete($componente[0])) {
            return true;
        }

        return false;
    }

    public function atualizaExclusoesDeComponentes($serieId, $componentes)
    {
        $escolas = $this->getEscolasDaSerie($serieId);
        $turmas = $this->getTurmasDaSerieNoAnoLetivoAtual($serieId);

        if ($escolas && $componentes) {
            foreach ($escolas as $escola) {
                foreach ($componentes as $componente) {
                    $this->excluiEscolaSerieDisciplina($escola['ref_cod_escola'], $serieId, $componente);
                }
            }
        }

        if ($turmas && $componentes) {
            foreach ($turmas as $turma) {
                foreach ($componentes as $componente) {
                    $this->excluiComponenteDaTurma($componente, $turma['cod_turma']);
                }
            }
        }
    }

    public function excluiComponentesSerie()
    {
        $serieId = $this->getRequest()->serie_id;
        $obj = new clsModulesComponenteCurricularAnoEscolar(null, $serieId);

        if ($obj->exclui()) {
            $this->excluiTodosComponenteDaTurma($serieId);
            $this->excluiTodasDisciplinasEscolaSerie($serieId);
        }
    }

    public function excluiTodasDisciplinasEscolaSerie($serieId)
    {
        $escolas = $this->getEscolasDaSerie($serieId);

        if ($escolas) {
            foreach ($escolas as $escola) {
                $objEscolaSerieDisciplina = new clsPmieducarEscolaSerieDisciplina($serieId, $escola['ref_cod_escola']);
                $objEscolaSerieDisciplina->excluirTodos();
            }
        }
    }

    public function excluiTodosComponenteDaTurma($serieId)
    {
        $turmas = $this->getTurmasDaSerieNoAnoLetivoAtual($serieId);
        $mapper = new ComponenteCurricular_Model_TurmaDataMapper();

        if ($turmas) {
            foreach ($turmas as $turma) {
                $where = ['turma_id' => $turma['cod_turma']];
                $componentes = $mapper->findAll(['componente_curricular_id', 'turma_id'], $where, [], false);
            }
        }

        if ($componentes) {
            foreach ($componentes as $componente) {
                $mapper->delete($componente);
            }
        }
    }

    public function existeDependencia()
    {
        $serie = $this->getRequest()->serie_id;
        $escola = $this->getRequest()->escola_id;
        $disciplinas = $this->getRequest()->disciplinas;
        $disciplinas = explode(',', $disciplinas);
        $obj = new clsPmieducarEscolaSerieDisciplina($serie, $escola, null, 1);

        return ['existe_dependencia' => $obj->existeDependencia($disciplinas)];
    }

    public function existeDispensa()
    {
        $serie = $this->getRequest()->serie_id;
        $escola = $this->getRequest()->escola_id;
        $disciplinas = $this->getRequest()->disciplinas;
        $disciplinas = explode(',', $disciplinas);
        $obj = new clsPmieducarEscolaSerieDisciplina($serie, $escola, null, 1);

        return ['existe_dispensa' => $obj->existeDispensa($disciplinas)];
    }

    public function Gerar()
    {
        if ($this->isRequestFor('post', 'atualiza-componentes-serie')) {
            $this->appendResponse($this->atualizaComponentesDaSerie());
        } elseif ($this->isRequestFor('post', 'replica-componentes-adicionados-escolas')) {
            $this->appendResponse($this->atualizaEscolasSerieDisciplina());
        } elseif ($this->isRequestFor('post', 'exclui-componentes-serie')) {
            $this->appendResponse($this->excluiComponentesSerie());
        } elseif ($this->isRequestFor('get', 'existe-dispensa')) {
            $this->appendResponse($this->existeDispensa());
        } elseif ($this->isRequestFor('get', 'existe-dependencia')) {
            $this->appendResponse($this->existeDependencia());
        } else {
            $this->notImplementedOperationError();
        }
    }
}
