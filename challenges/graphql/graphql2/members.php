<?php
declare(strict_types=1);
// ============================================================
// graphql2 · members.php —— 数据层（图书/会员，含登录口令）
// admin(id=1) 的 secretNote 是 flag；实验账号 bob / bob123（页面告知）。
// ============================================================

const GQL2_FLAG = 'flag{graphql2_2e06bee4}';

/** @return array<int, array> 以 id 为键的图书表 */
function gql2_books(): array
{
    return [
        1 => ['id' => 1, 'title' => '三体·黑暗森林', 'year' => 2008],
        2 => ['id' => 2, 'title' => '宇宙尽头的餐馆', 'year' => 1980],
        3 => ['id' => 3, 'title' => '银河系搭车客指南', 'year' => 1979],
    ];
}

/** @return array<string, array> 以 id 为键的会员表 */
function gql2_members(): array
{
    return [
        '1' => ['id' => '1', 'username' => 'admin', 'email' => 'admin@stars-lib.example',
                'password' => '???', 'borrowed' => [], 'secretNote' => GQL2_FLAG],
        '2' => ['id' => '2', 'username' => 'bob', 'email' => 'bob@stars-lib.example',
                'password' => 'bob123', 'borrowed' => [1, 2], 'secretNote' => '记得还书'],
        '3' => ['id' => '3', 'username' => 'carol', 'email' => 'carol@stars-lib.example',
                'password' => 'carol123', 'borrowed' => [3], 'secretNote' => '无'],
    ];
}

/** 从会话取当前登录会员（未登录返回 null） */
function gql2_current_member(): ?array
{
    $mid = (isset($_SESSION['gql2_mid']) && is_string($_SESSION['gql2_mid'])) ? $_SESSION['gql2_mid'] : '';
    if ($mid === '') {
        return null;
    }
    $members = gql2_members();
    return $members[$mid] ?? null;
}
