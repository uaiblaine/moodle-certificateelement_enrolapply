<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for the enrolment application answer element.
 *
 * @package    certificateelement_enrolapply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['knownshortnames'] = 'Nomes breves em uso neste site: {$a}';
$string['pluginname'] = 'Resposta da solicitação de inscrição';
$string['privacy:metadata:issues'] = 'A tabela de certificados emitidos da ferramenta de certificados, em cujos dados o elemento de resposta da solicitação de inscrição guarda uma cópia das respostas que o certificado imprime.';
$string['privacy:metadata:issues:data'] = 'As respostas às perguntas da solicitação de inscrição que o certificado imprime, como eram quando ele foi emitido.';
$string['privacy:metadata:issues:userid'] = 'O usuário para quem o certificado foi emitido.';
$string['shortname'] = 'Nome breve da pergunta';
$string['shortname_help'] = 'O nome breve da pergunta da solicitação cuja resposta é impressa, como definido nas configurações da própria pergunta no método de inscrição.

* A resposta vem da solicitação aprovada do participante no curso para o qual o certificado é emitido.
* Perguntas de cursos diferentes respondem ao mesmo elemento quando têm o mesmo nome breve; uma pergunta compartilhada tem um só nome breve no site todo.
* A resposta é impressa como a solicitação a registrou: "Sim" ou "Não" para uma caixa de seleção, a opção escolhida para um menu, a data por extenso para uma data.
* A resposta é fixada quando o certificado é emitido.

**Exemplo**: "unidade" imprime "3º Batalhão" para o participante que deu essa resposta à pergunta cujo nome breve é "unidade".

A resposta impressa é lida por todos que podem ver o certificado ou verificá-lo pelo código, e não só por quem decide as solicitações; por isso, indique apenas perguntas feitas para um certificado.

Nada é impresso para quem foi inscrito de outra forma ou deixou a pergunta sem resposta.';
$string['shortnameinvalid'] = 'Um nome breve tem só letras minúsculas, números e sublinhados, com até 100 caracteres.';
