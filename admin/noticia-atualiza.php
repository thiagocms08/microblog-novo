<?php

use Microblog\Auth\ControleDeAcesso;
use Microblog\Enums\Destaque;
use Microblog\Enums\TipoUsuario;
use Microblog\Helpers\Utils;
use Microblog\Helpers\Validacoes;
use Microblog\Services\CategoriaServico;
use Microblog\Services\NoticiaServico;
use Microblog\Models\Noticia;

require_once __DIR__ . '/../vendor/autoload.php';

ControleDeAcesso::exigirLogin();

$mensagemErro = "";
$dadosNoticia = null;
$listaDeCategorias = [];
$categoriasCarregadas = false;

$noticiaId = Utils::normalizarEntrada($_GET['id'] ?? null, 'inteiro');
Utils::verificarId($noticiaId);

try {
    $tipoUsuario = TipoUsuario::from($_SESSION['tipo']);
    $usuarioId = (int) $_SESSION['id'];

    $noticiaServico = new NoticiaServico();
    $dadosNoticia = $noticiaServico->buscarPorId($tipoUsuario, $usuarioId, $noticiaId);

    if ($dadosNoticia === null) {
        $mensagemErro = "Notícia não encontrada ou você não tem permissão para acessá-la.";
    }
} catch (Throwable $e) {
    Utils::registrarLog($e);
    $mensagemErro = "Não foi possível carregar os dados da notícia.";
}

if ($dadosNoticia !== null && $mensagemErro === "") {
    try {
        $categoriaServico = new CategoriaServico();
        $listaDeCategorias = $categoriaServico->listarTodos();
        $categoriasCarregadas = true;
    } catch (Throwable $e) {
        Utils::registrarLog($e);
        $mensagemErro = "Não foi possível carregar a lista de categorias.";
    }
}

$titulo = $dadosNoticia['titulo'] ?? '';
$texto = $dadosNoticia['texto'] ?? '';
$resumo = $dadosNoticia['resumo'] ?? '';
$imagemAtual = $dadosNoticia['imagem'] ?? '';
$categoriaSelecionada = (int) ($dadosNoticia['categoria_id'] ?? 0);
$destaqueSelecionado = $dadosNoticia['destaque'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dadosNoticia !== null && $categoriasCarregadas) {
    try {
        $titulo = Utils::normalizarEntrada($_POST['titulo'] ?? '');
        $texto = Utils::normalizarEntrada($_POST['texto'] ?? '');
        $resumo = Utils::normalizarEntrada($_POST['resumo'] ?? '');
        $categoriaSelecionada = (int) Utils::normalizarEntrada($_POST['categoria'] ?? null, 'inteiro');
        $destaqueSelecionado = Utils::normalizarEntrada($_POST['destaque'] ?? '');

        Validacoes::validarTitulo($titulo);
        Validacoes::validarTexto($texto);
        Validacoes::validarResumo($resumo);
        Validacoes::validarCategoria($categoriaSelecionada);
        Validacoes::validarDestaque($destaqueSelecionado);

        $categoriaExiste = false;
        foreach ($listaDeCategorias as $itemCategoria) {
            if ((int) $itemCategoria['id'] === $categoriaSelecionada) {
                $categoriaExiste = true;
                break;
            }
        }

        if (!$categoriaExiste) {
            throw new InvalidArgumentException("Selecione uma categoria válida.");
        }

        $imagem = $imagemAtual;
        if (($_FILES['imagem']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $imagem = Utils::upload($_FILES['imagem'] ?? null);
        }

        $noticia = new Noticia(
            $titulo,
            $texto,
            $resumo,
            $imagem,
            Destaque::from($destaqueSelecionado),
            (int) $dadosNoticia['usuario_id'],
            $categoriaSelecionada,
            $noticiaId,
            $dadosNoticia['data']
        );

        $noticiaServico->atualizar($noticia, $tipoUsuario, $usuarioId);
        header("location:noticias.php");
        exit;
    } catch (InvalidArgumentException $e) {
        $mensagemErro = $e->getMessage();
    } catch (Throwable $e) {
        Utils::registrarLog($e);
        $mensagemErro = "Não foi possível atualizar a notícia. Tente novamente.";
    }
}

require_once __DIR__ . '/../includes/cabecalho-admin.php';
?>

<div class="row">
    <article class="col-12 bg-white rounded shadow my-1 py-4">
        <h2 class="text-center">Atualizar dados da notícia</h2>

        <?php if ($mensagemErro !== ""): ?>
            <div class="alert alert-danger text-center">
                <?= Utils::escaparHtml($mensagemErro) ?>
            </div>
        <?php endif; ?>

        <?php if ($dadosNoticia !== null && $categoriasCarregadas): ?>
            <p class="text-center">
                Publicada em <?= Utils::escaparHtml(Utils::formataData($dadosNoticia['data'])) ?>
                <?php if (!empty($dadosNoticia['autor'])): ?>
                    por <?= Utils::escaparHtml($dadosNoticia['autor']) ?>
                <?php endif; ?>
            </p>

            <form class="mx-auto w-75" action="noticia-atualiza.php?id=<?= Utils::escaparHtml($noticiaId) ?>" method="post" id="form-atualizar" name="form-atualizar" enctype="multipart/form-data">

                <div class="mb-3">
                    <label class="form-label" for="categoria">Categoria:</label>
                    <select class="form-select" name="categoria" id="categoria">
                        <?php foreach ($listaDeCategorias as $itemCategoria): ?>
                            <option
                                value="<?= Utils::escaparHtml($itemCategoria['id']) ?>"
                                <?= (int) $itemCategoria['id'] === $categoriaSelecionada ? 'selected' : '' ?>
                            >
                                <?= Utils::escaparHtml($itemCategoria['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="titulo">Título:</label>
                    <input
                        value="<?= Utils::escaparHtml($titulo) ?>"
                        class="form-control"
                        type="text"
                        id="titulo"
                        name="titulo"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label" for="texto">Texto:</label>
                    <textarea class="form-control" name="texto" id="texto" cols="50" rows="6"><?= Utils::escaparHtml($texto) ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="resumo">Resumo (máximo de 300 caracteres):</label>
                    <span id="maximo" class="badge bg-danger"><?= mb_strlen($resumo) ?></span>
                    <textarea class="form-control" name="resumo" id="resumo" cols="50" rows="2" maxlength="300"><?= Utils::escaparHtml($resumo) ?></textarea>
                </div>

                <div class="mb-3">
                    <div class="row">
                        <div class="col-5">
                            <label for="imagem-existente" class="form-label">Imagem atual:</label>
                            <input
                                value="<?= Utils::escaparHtml($imagemAtual) ?>"
                                class="form-control"
                                type="text"
                                id="imagem-existente"
                                readonly
                                disabled
                            >
                        </div>
                        <div class="col-7 text-end">
                            <img
                                src="../images/<?= rawurlencode($imagemAtual) ?>"
                                alt="Imagem atual da notícia"
                                class="img-fluid rounded"
                            >
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="imagem" class="form-label">Caso queira mudar, selecione outra imagem:</label>
                    <input class="form-control" type="file" id="imagem" name="imagem" accept="image/jpeg, image/gif, image/png, image/webp">
                </div>

                <div class="mb-3">
                    <p>Deixar a notícia em destaque?</p>
                    <input
                        type="radio"
                        class="btn-check"
                        name="destaque"
                        id="option1"
                        autocomplete="off"
                        value="nao"
                        <?= $destaqueSelecionado === 'nao' ? 'checked' : '' ?>
                    >
                    <label class="btn btn-outline-danger" for="option1">Não</label>
                    <input
                        type="radio"
                        class="btn-check"
                        name="destaque"
                        id="option2"
                        autocomplete="off"
                        value="sim"
                        <?= $destaqueSelecionado === 'sim' ? 'checked' : '' ?>
                    >
                    <label class="btn btn-outline-success" for="option2">Sim</label>
                </div>

                <button class="btn btn-primary" type="submit" name="atualizar">
                    <i class="bi bi-arrow-clockwise"></i> Atualizar
                </button>
            </form>
        <?php endif; ?>
    </article>
</div>

<script src="js/contador.js"></script>
<?php require_once __DIR__ . '/../includes/rodape-admin.php'; ?>
