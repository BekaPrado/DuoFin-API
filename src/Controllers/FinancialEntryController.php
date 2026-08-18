<?php

namespace App\Controllers;

use App\Database;
use PDO;

class FinancialEntryController
{
    public static function index(object $auth): void
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT
                id,
                couple_id,
                created_by_user_id,
                responsible_user_id,
                account_id,
                category_id,
                type,
                description,
                amount,
                transaction_date,
                payment_method,
                expense_type,
                notes,
                created_at,
                updated_at
            FROM financial_entries
            WHERE couple_id = :couple_id
            ORDER BY transaction_date DESC, id DESC
        ");

        $stmt->execute([
            'couple_id' => $auth->couple_id
        ]);

        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $entries
        ]);
    }

    public static function store(object $auth): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $responsibleUserId = $input['responsible_user_id'] ?? null;
    $accountId = $input['account_id'] ?? null;
    $categoryId = $input['category_id'] ?? null;
    $type = $input['type'] ?? '';
    $description = trim($input['description'] ?? '');
    $amount = $input['amount'] ?? null;
    $transactionDate = $input['transaction_date'] ?? '';
    $paymentMethod = $input['payment_method'] ?? null;
    $expenseType = $input['expense_type'] ?? null;
    $notes = trim($input['notes'] ?? '');

    // Campos obrigatórios
    if (
        !$responsibleUserId ||
        !$accountId ||
        !$categoryId ||
        !$type ||
        !$description ||
        $amount === null ||
        !$transactionDate
    ) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Preencha todos os campos obrigatórios.'
        ]);

        return;
    }

    // Tipo do lançamento
    if (!in_array($type, ['income', 'expense'], true)) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Tipo de lançamento inválido.'
        ]);

        return;
    }

    // Forma de pagamento
    if (
        $paymentMethod !== null &&
        !in_array(
            $paymentMethod,
            ['pix', 'debit', 'credit', 'cash', 'transfer', 'other'],
            true
        )
    ) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Forma de pagamento inválida.'
        ]);

        return;
    }

    // Tipo da despesa
    if (
        $expenseType !== null &&
        !in_array(
            $expenseType,
            ['fixed', 'variable', 'recurring'],
            true
        )
    ) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Tipo de despesa inválido.'
        ]);

        return;
    }

    if (!is_numeric($amount) || $amount <= 0) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'O valor deve ser maior que zero.'
        ]);

        return;
    }

    $pdo = Database::connect();

    /*
     * Verifica se o usuário responsável
     * pertence ao mesmo casal.
     */
    $userStmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE id = :user_id
        AND couple_id = :couple_id
        AND is_active = true
    ");

    $userStmt->execute([
        'user_id' => $responsibleUserId,
        'couple_id' => $auth->couple_id
    ]);

    if (!$userStmt->fetch()) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Usuário responsável inválido.'
        ]);

        return;
    }

    /*
     * Verifica se a conta pertence ao casal.
     */
    $accountStmt = $pdo->prepare("
        SELECT id
        FROM accounts
        WHERE id = :account_id
        AND couple_id = :couple_id
        AND is_active = true
    ");

    $accountStmt->execute([
        'account_id' => $accountId,
        'couple_id' => $auth->couple_id
    ]);

    if (!$accountStmt->fetch()) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Conta inválida.'
        ]);

        return;
    }

    /*
     * Verifica se a categoria pertence ao casal.
     */
    $categoryStmt = $pdo->prepare("
        SELECT id
        FROM categories
        WHERE id = :category_id
        AND couple_id = :couple_id
    ");

    $categoryStmt->execute([
        'category_id' => $categoryId,
        'couple_id' => $auth->couple_id
    ]);

    if (!$categoryStmt->fetch()) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Categoria inválida.'
        ]);

        return;
    }

    /*
     * Cria o lançamento.
     *
     * created_by_user_id vem do JWT.
     * responsible_user_id vem do usuário selecionado
     * no cadastro do lançamento.
     */
    try {

        $pdo->beginTransaction();
    
        /*
         * 1. Cria o lançamento
         */
        $stmt = $pdo->prepare("
            INSERT INTO financial_entries (
                couple_id,
                created_by_user_id,
                responsible_user_id,
                account_id,
                category_id,
                type,
                description,
                amount,
                transaction_date,
                payment_method,
                expense_type,
                notes
            )
            VALUES (
                :couple_id,
                :created_by_user_id,
                :responsible_user_id,
                :account_id,
                :category_id,
                :type,
                :description,
                :amount,
                :transaction_date,
                :payment_method,
                :expense_type,
                :notes
            )
            RETURNING
                id,
                couple_id,
                created_by_user_id,
                responsible_user_id,
                account_id,
                category_id,
                type,
                description,
                amount,
                transaction_date,
                payment_method,
                expense_type,
                notes,
                created_at,
                updated_at
        ");
    
        $stmt->execute([
            'couple_id' => $auth->couple_id,
            'created_by_user_id' => $auth->user_id,
            'responsible_user_id' => $responsibleUserId,
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'type' => $type,
            'description' => $description,
            'amount' => $amount,
            'transaction_date' => $transactionDate,
            'payment_method' => $paymentMethod,
            'expense_type' => $expenseType,
            'notes' => $notes ?: null
        ]);
    
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    
        /*
         * 2. Define o impacto no saldo
         *
         * expense = diminui
         * income  = aumenta
         */
        $balanceChange = $type === 'expense'
            ? -abs((float) $amount)
            : abs((float) $amount);
    
        /*
         * 3. Atualiza o saldo da conta
         *
         * Também verificamos o couple_id para garantir
         * que a conta pertence ao casal.
         */
        $balanceStmt = $pdo->prepare("
            UPDATE accounts
            SET
                balance = balance + :balance_change,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :account_id
            AND couple_id = :couple_id
            AND is_active = true
        ");
    
        $balanceStmt->execute([
            'balance_change' => $balanceChange,
            'account_id' => $accountId,
            'couple_id' => $auth->couple_id
        ]);
    
        /*
         * 4. Confirma tudo
         */
        $pdo->commit();
    
        echo json_encode([
            'success' => true,
            'message' => 'Lançamento criado com sucesso.',
            'data' => $entry
        ]);
    
    } catch (Throwable $e) {
    
        /*
         * Se alguma coisa falhar,
         * desfazemos o lançamento e o saldo.
         */
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    
        http_response_code(500);
    
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao criar lançamento.',
            'error' => $e->getMessage()
        ]);
    }
}

public static function update(object $auth, int $id): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $responsibleUserId = $input['responsible_user_id'] ?? null;
    $accountId = $input['account_id'] ?? null;
    $categoryId = $input['category_id'] ?? null;
    $type = $input['type'] ?? '';
    $description = trim($input['description'] ?? '');
    $amount = $input['amount'] ?? null;
    $transactionDate = $input['transaction_date'] ?? '';
    $paymentMethod = $input['payment_method'] ?? null;
    $expenseType = $input['expense_type'] ?? null;
    $notes = trim($input['notes'] ?? '');

    // Campos obrigatórios
    if (
        !$responsibleUserId ||
        !$accountId ||
        !$categoryId ||
        !$type ||
        !$description ||
        $amount === null ||
        !$transactionDate
    ) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Preencha todos os campos obrigatórios.'
        ]);

        return;
    }

    // Tipo do lançamento
    if (!in_array($type, ['income', 'expense'], true)) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Tipo de lançamento inválido.'
        ]);

        return;
    }

    // Forma de pagamento
    if (
        $paymentMethod !== null &&
        !in_array(
            $paymentMethod,
            ['pix', 'debit', 'credit', 'cash', 'transfer', 'other'],
            true
        )
    ) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Forma de pagamento inválida.'
        ]);

        return;
    }

    // Tipo da despesa
    if (
        $expenseType !== null &&
        !in_array(
            $expenseType,
            ['fixed', 'variable', 'recurring'],
            true
        )
    ) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Tipo de despesa inválido.'
        ]);

        return;
    }

    // Valor
    if (!is_numeric($amount) || $amount <= 0) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'O valor deve ser maior que zero.'
        ]);

        return;
    }

    $pdo = Database::connect();

    /*
     * BUSCA O LANÇAMENTO ANTIGO
     *
     * Precisamos saber:
     * - conta antiga
     * - tipo antigo
     * - valor antigo
     */
    $entryStmt = $pdo->prepare("
        SELECT
            id,
            account_id,
            type,
            amount
        FROM financial_entries
        WHERE id = :id
        AND couple_id = :couple_id
    ");

    $entryStmt->execute([
        'id' => $id,
        'couple_id' => $auth->couple_id
    ]);

    $oldEntry = $entryStmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldEntry) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Lançamento não encontrado.'
        ]);

        return;
    }

    /*
     * VERIFICA O USUÁRIO RESPONSÁVEL
     */
    $userStmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE id = :user_id
        AND couple_id = :couple_id
        AND is_active = true
    ");

    $userStmt->execute([
        'user_id' => $responsibleUserId,
        'couple_id' => $auth->couple_id
    ]);

    if (!$userStmt->fetch()) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Usuário responsável inválido.'
        ]);

        return;
    }

    /*
     * VERIFICA A NOVA CONTA
     */
    $accountStmt = $pdo->prepare("
        SELECT id
        FROM accounts
        WHERE id = :account_id
        AND couple_id = :couple_id
        AND is_active = true
    ");

    $accountStmt->execute([
        'account_id' => $accountId,
        'couple_id' => $auth->couple_id
    ]);

    if (!$accountStmt->fetch()) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Conta inválida.'
        ]);

        return;
    }

    /*
     * VERIFICA A CATEGORIA
     */
    $categoryStmt = $pdo->prepare("
        SELECT id
        FROM categories
        WHERE id = :category_id
        AND couple_id = :couple_id
    ");

    $categoryStmt->execute([
        'category_id' => $categoryId,
        'couple_id' => $auth->couple_id
    ]);

    if (!$categoryStmt->fetch()) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Categoria inválida.'
        ]);

        return;
    }

    try {

        /*
         * INICIA TRANSAÇÃO
         */
        $pdo->beginTransaction();

        /*
         * 1. REVERTE O IMPACTO ANTIGO
         *
         * Se era expense:
         *     saldo recebeu -50
         *     agora precisamos devolver +50
         *
         * Se era income:
         *     saldo recebeu +500
         *     agora precisamos retirar -500
         */
        $oldBalanceChange = $oldEntry['type'] === 'expense'
            ? -abs((float) $oldEntry['amount'])
            : abs((float) $oldEntry['amount']);

        $reverseOldBalance = -$oldBalanceChange;

        $reverseStmt = $pdo->prepare("
            UPDATE accounts
            SET
                balance = balance + :balance_change,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :account_id
            AND couple_id = :couple_id
        ");

        $reverseStmt->execute([
            'balance_change' => $reverseOldBalance,
            'account_id' => $oldEntry['account_id'],
            'couple_id' => $auth->couple_id
        ]);

        /*
         * 2. ATUALIZA O LANÇAMENTO
         */
        $stmt = $pdo->prepare("
            UPDATE financial_entries
            SET
                responsible_user_id = :responsible_user_id,
                account_id = :account_id,
                category_id = :category_id,
                type = :type,
                description = :description,
                amount = :amount,
                transaction_date = :transaction_date,
                payment_method = :payment_method,
                expense_type = :expense_type,
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
            AND couple_id = :couple_id
            RETURNING
                id,
                couple_id,
                created_by_user_id,
                responsible_user_id,
                account_id,
                category_id,
                type,
                description,
                amount,
                transaction_date,
                payment_method,
                expense_type,
                notes,
                created_at,
                updated_at
        ");

        $stmt->execute([
            'id' => $id,
            'couple_id' => $auth->couple_id,
            'responsible_user_id' => $responsibleUserId,
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'type' => $type,
            'description' => $description,
            'amount' => $amount,
            'transaction_date' => $transactionDate,
            'payment_method' => $paymentMethod,
            'expense_type' => $expenseType,
            'notes' => $notes ?: null
        ]);

        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        /*
         * 3. APLICA O NOVO IMPACTO
         */
        $newBalanceChange = $type === 'expense'
            ? -abs((float) $amount)
            : abs((float) $amount);

        $newBalanceStmt = $pdo->prepare("
            UPDATE accounts
            SET
                balance = balance + :balance_change,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :account_id
            AND couple_id = :couple_id
            AND is_active = true
        ");

        $newBalanceStmt->execute([
            'balance_change' => $newBalanceChange,
            'account_id' => $accountId,
            'couple_id' => $auth->couple_id
        ]);

        /*
         * 4. CONFIRMA TODAS AS ALTERAÇÕES
         */
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Lançamento atualizado com sucesso.',
            'data' => $entry
        ]);

    } catch (Throwable $e) {

        /*
         * Se algo der errado,
         * desfazemos tudo.
         */
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Erro ao atualizar lançamento.',
            'error' => $e->getMessage()
        ]);
    }
}

public static function delete(object $auth, int $id): void
{
    $pdo = Database::connect();

    /*
     * Busca o lançamento antes de excluir.
     * Precisamos dessas informações para
     * reverter o saldo da conta.
     */
    $checkStmt = $pdo->prepare("
        SELECT
            id,
            account_id,
            type,
            amount
        FROM financial_entries
        WHERE id = :id
        AND couple_id = :couple_id
    ");

    $checkStmt->execute([
        'id' => $id,
        'couple_id' => $auth->couple_id
    ]);

    $entry = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$entry) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Lançamento não encontrado.'
        ]);

        return;
    }

    /*
     * Reverte o impacto do lançamento no saldo.
     *
     * Receita:
     * saldo - valor
     *
     * Despesa:
     * saldo + valor
     */
    if ($entry['type'] === 'income') {

        $balanceStmt = $pdo->prepare("
            UPDATE accounts
            SET
                balance = balance - :amount,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :account_id
            AND couple_id = :couple_id
        ");

    } else {

        $balanceStmt = $pdo->prepare("
            UPDATE accounts
            SET
                balance = balance + :amount,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :account_id
            AND couple_id = :couple_id
        ");
    }

    $balanceStmt->execute([
        'amount' => $entry['amount'],
        'account_id' => $entry['account_id'],
        'couple_id' => $auth->couple_id
    ]);

    /*
     * Depois de reverter o saldo,
     * excluímos o lançamento.
     */
    $stmt = $pdo->prepare("
        DELETE FROM financial_entries
        WHERE id = :id
        AND couple_id = :couple_id
    ");

    $stmt->execute([
        'id' => $id,
        'couple_id' => $auth->couple_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Lançamento excluído com sucesso.'
    ]);
}

}