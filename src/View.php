<?php

declare(strict_types=1);

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $contentTemplate = CRM_ROOT.'/templates/'.$template.'.php';
        if (! is_file($contentTemplate)) {
            throw new RuntimeException("View {$template} was not found.");
        }
        require CRM_ROOT.'/templates/layout.php';
        unset($_SESSION['old'], $_SESSION['errors'], $_SESSION['flash']);
    }
}
