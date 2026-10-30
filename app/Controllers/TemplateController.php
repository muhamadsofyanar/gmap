<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\MessageTemplateModel;

class TemplateController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->view('template/index', [
            'pageTitle' => 'Template WhatsApp - Gmap Prospek',
            'templates' => (new MessageTemplateModel())->all()
        ]);
    }

    public function save(): void
    {
        $this->requireAuth();
        $this->validateCsrf();
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $name = $_POST['name'] ?? null;
        $body = $_POST['body'] ?? null;
        if ($id === false || !is_string($name) || !is_string($body)) {
            $this->json(['success' => false, 'message' => 'Data template tidak valid.'], 422);
        }
        try {
            $id = (new MessageTemplateModel())->save($id, $name, $body);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        $this->json(['success' => true, 'id' => $id]);
    }
}
