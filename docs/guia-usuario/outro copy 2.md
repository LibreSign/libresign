# Guia do usuário

O NextCloud permite que o fluxo de processos possa ser organizado de diferentes formas;

Esse documento visa exemplificar um possivel fluxo para a gestão dos processos, e explicar alguns conceitos do NextCloud.
Para mais detalhes, veja a Documentação Oficial do NextCloud.

## Usuarios e Grupos e Compartilhamento

Todo recurso dentro do NextCloud é vinculado diretamente ao usuário que o criou/adicionou, o que significa que, para que outros usuários tenham acesso a um recurso, o usuario dono precisa primeiro habilitar o compartilhamento deste recurso.

![Opções de Compartilhamento](./imagens/opcoes-compartilhamento.png)

É possivel compartilhar com um usuário específico ou com vários de uma vez, através dos grupos de usuário, cadastrados pelo administrador.

As acões que podem ser tomadas em um recurso compartilhado (exibição, modificação, remoção) são definidas somente pelo dono do recurso, assim, para que seja possivel manter um controle do que pode ser acessado e por quem, recomenda-se que todos os recursos bases sejam criados por um usuário administrador, para que este administrador possa definir os grupos e usuários que terão acesso ao recurso, e que nivel de acesso cada um terá.

O Administrador pode também configurar o comportamento geral do compartilhamento de recursos. Estas configurações estão na sessão `Administração > Compartilhamento`.

![Configuração do comportamento de compartilhamento](./imagens/configuracao-admin-compartilhamento.png)

Um conceito primordial no uso do NextCloud é o compartilhamento.

Um usuário pode compartilhar qualquer arquivo, calendário, contato ou painel do Deck que ele tenha criado ou feito o upload.

Os compartilhamentos podem ser feitos com usuarios diretamente ou com grupo de usuários.

Além de permitir a visualização do item, também é possivel permitir edição, exclusão e recompartilhamento com outros, além de ser possivel setar uma data de vencimento para o elemento.

É possivel ajustar o comportamento do compartilhamento na sessão "Compartilhamento", dentro das configurações de Administração.

Agrupando os usuários, é possivel controlar o que é compartilhado dentro do grupo.

-- talvez explicar melhor sobre pq?

O fluxo que recomendamos é:

1. administrador cadastra usuários;
2. administrador agrupa usuários;
3. administrador cria as estruturas bases;
4. administrador libera acesso as estruturas aos usuarios de acordo com a permissão de cada grupo;
5. usuários gerenciam os itens relativos aos processos;
6. administradores adicionam ou removem permissões de usuários conforme necessidade;

## Adicionar Grupos, usuários e permissões básicas

Ao clicar no icone do usuário, no canto superior direito, o administrador pode adicionar novos usuários;

![Menu Usuário](./imagens/menu-usuario-admin.png)

Neste painel é possivel visualizar todos os usuários cadastrados, e a que grupo cada um deles pertence/administra.

![Formulário Novo Usuário](./imagens/formulario-novo-usuario.png)

Ao cadastrar um novo usuário, é importante cadastrar uma senha, ou um email, para que, caso o usuário não tenha a senha, seja possivel para este usuário resetar através de um link que será enviado por email. Veja também: [configurando envio de emails](./configurar-email.md)

Além de ser possivel cadastrar o usuário num grupo, também é possivel adicionar este usuário como administrador de um ou mais grupos, o que irá permitir que este usuário gerencie outros usuários destes grupos.

No caso dos administradores do sistema, sempre devem pertencer ao grupo `admin`.

Caso o grupo informado não exista ainda, um novo será criado, tendo o novo usuário como unico integrante.

### Compartilhamento e controle de acesso

Todo recurso dentro do NextCloud é vinculado diretamente ao usuário que o criou/adicionou, o que significa que, para que outros usuários tenham acesso a um recurso, o usuario dono precisa primeiro habilitar o compartilhamento deste recurso.

![Opções de Compartilhamento](./imagens/opcoes-compartilhamento.png)

As acões que podem ser tomadas em um recurso compartilhado (exibição, modificação, remoção) são definidas somente pelo dono do recurso, de forma que, para se manter um controle do que pode ser acessado e por quem, recomenda-se que todos os recursos bases sejam criados pelo Administrador, para que este Administrador possa definir os grupos de usuário que terão acesso ao recurso, e que nivel de acesso cada grupo terá.

O Administrador pode configurar o comportamento geral do compartilhamento de recursos. Estas configurações estão na sessão `Administração > Compartilhamento`.

![Configuração do comportamento de compartilhamento](./imagens/configuracao-admin-compartilhamento.png)




### Organizar Arquivos do Processo

