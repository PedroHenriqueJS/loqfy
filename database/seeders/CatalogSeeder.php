<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use App\Models\Equipment;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $equipment = [
            ['Betoneira 400L', 'Concretagem', 'Betoneira elétrica de 400 litros, para lajes, contrapisos e obras médias', ['betoneira', 'betoneira grande', 'misturador de concreto'], 90, 4],
            ['Betoneira 150L', 'Concretagem', 'Betoneira elétrica de 150 litros, para reparos e pequenas obras', ['betoneira pequena'], 55, 3],
            ['Vibrador de concreto', 'Concretagem', 'Vibrador de imersão com mangote de 3,5 m', ['vibrador de imersão', 'mangote vibrador'], 60, 3],
            ['Alisadora de concreto', 'Concretagem', 'Acabadora de piso tipo helicóptero, 36 polegadas', ['helicóptero', 'acabadora de piso', 'bambolê'], 160, 1],
            ['Rolo compactador 1,5t', 'Compactação', 'Rolo compactador vibratório de 1,5 tonelada, para bases e asfalto', ['rolo compactador', 'rolo vibratório', 'rolinho'], 480, 1],
            ['Placa vibratória', 'Compactação', 'Placa compactadora a gasolina, para solo granular, paver e valas largas', ['compactador de placa', 'placa compactadora'], 110, 2],
            ['Compactador de solo tipo sapo', 'Compactação', 'Compactador de percussão, para valas estreitas e solo argiloso', ['sapo', 'sapinho', 'socador', 'compactador sapo'], 120, 2],
            ['Martelete rompedor 10kg', 'Demolição e perfuração', 'Martelete eletropneumático, para rasgos e pequenas demolições', ['martelete', 'rompedor pequeno'], 85, 4],
            ['Martelo demolidor 30kg', 'Demolição e perfuração', 'Demolidor elétrico de 30 kg, para pisos e concreto grosso', ['rompedor', 'martelo rompedor', 'demolidor', 'britadeira'], 150, 2],
            ['Furadeira de impacto', 'Demolição e perfuração', 'Furadeira de impacto 1/2 polegada', ['furadeira'], 30, 6],
            ['Perfuratriz de solo', 'Demolição e perfuração', 'Trado motorizado a gasolina, para furos de mourão e brocas', ['trado motorizado', 'perfurador de solo', 'trado'], 140, 1],
            ['Serra mármore', 'Corte', 'Serra mármore 4 3/8 polegadas, para piso, porcelanato e telha', ['makita', 'serra de piso'], 35, 5],
            ['Serra circular', 'Corte', 'Serra circular 7 1/4 polegadas, para madeira', ['serra de madeira'], 35, 4],
            ['Esmerilhadeira 7 polegadas', 'Corte', 'Esmerilhadeira angular, para corte e desbaste de metal', ['esmerilhadeira', 'lixadeira', 'esmeril', 'rebarbadora'], 35, 5],
            ['Cortadora de piso', 'Corte', 'Cortadora a gasolina com disco de 350 mm, para concreto e asfalto', ['serra de asfalto', 'cortadora de asfalto', 'serra clipper'], 180, 1],
            ['Policorte', 'Corte', 'Serra de corte rápido de bancada, para vergalhão e perfis metálicos', ['corte de ferro', 'serra policorte'], 50, 2],
            ['Andaime tubular 1,0 x 1,5m', 'Acesso e elevação', 'Painel de andaime tubular; a diária é por painel', ['andaime', 'painel de andaime', 'torre de andaime'], 8, 60],
            ['Escada extensível 7m', 'Acesso e elevação', 'Escada extensível de alumínio, 7 metros', ['escada', 'escada de alumínio'], 30, 4],
            ['Guincho de coluna 300kg', 'Acesso e elevação', 'Guincho elétrico de coluna, para subir material até 300 kg', ['guincho', 'talha elétrica'], 95, 2],
            ['Escora metálica', 'Acesso e elevação', 'Escora metálica regulável de 2,0 a 3,2 m; a diária é por unidade', ['escora', 'escoramento', 'pontalete metálico'], 2.5, 200],
            ['Gerador 5 kVA', 'Energia e ar', 'Gerador a gasolina de 5 kVA, para ferramentas elétricas', ['gerador', 'gerador a gasolina'], 130, 2],
            ['Gerador 12 kVA', 'Energia e ar', 'Gerador a diesel de 12 kVA, para betoneiras e equipamentos trifásicos', ['gerador grande', 'gerador diesel'], 260, 1],
            ['Compressor de ar 10 pés', 'Energia e ar', 'Compressor de ar de 10 pés com reservatório de 100 litros', ['compressor'], 80, 2],
            ['Lavadora de alta pressão', 'Limpeza e acabamento', 'Lavadora de alta pressão profissional', ['lava jato', 'wap', 'hidrolavadora'], 70, 3],
            ['Aspirador industrial', 'Limpeza e acabamento', 'Aspirador de pó e água de 50 litros', ['aspirador', 'aspirador de pó'], 60, 2],
            ['Lixadeira de parede', 'Limpeza e acabamento', 'Lixadeira de parede e teto com haste, para massa corrida', ['lixadeira de teto', 'lixadeira girafa'], 75, 2],
            ['Politriz de piso', 'Limpeza e acabamento', 'Politriz para polimento de pisos', ['enceradeira industrial'], 90, 1],
            ['Bomba submersível', 'Bombeamento', 'Bomba submersível elétrica, para esgotar valas e subsolos', ['bomba sapo', 'bomba de água', 'bomba de drenagem'], 65, 3],
            ['Motobomba a gasolina', 'Bombeamento', 'Motobomba autoescorvante de 2 polegadas', ['motobomba', 'bomba de sucção'], 95, 2],
            ['Carrinho de mão', 'Movimentação', 'Carrinho de mão de 65 litros com pneu', ['carriola', 'carrinho de pedreiro'], 12, 10],
        ];

        foreach ($equipment as [$name, $category, $description, $aliases, $rate, $quantity]) {
            Equipment::create([
                'name' => $name,
                'category' => $category,
                'description' => $description,
                'aliases' => $aliases,
                'daily_rate' => $rate,
                'quantity_total' => $quantity,
            ]);
        }

        $zones = [
            ['Zona 1: São José e Continente', ['São José', 'Kobrasol', 'Campinas', 'Barreiros', 'Roçado', 'Estreito', 'Coqueiros', 'Capoeiras'], 40],
            ['Zona 2: Centro e bairros centrais da Ilha', ['Centro', 'Florianópolis', 'Trindade', 'Agronômica', 'Itacorubi', 'Córrego Grande', 'Santa Mônica', 'Pantanal', 'Saco dos Limões'], 60],
            ['Zona 3: Palhoça e Biguaçu', ['Palhoça', 'Pedra Branca', 'Ponte do Imaruim', 'Pagani', 'Biguaçu'], 70],
            ['Zona 4: Norte e Sul da Ilha', ['Ingleses', 'Canasvieiras', 'Jurerê', 'Santo Antônio de Lisboa', 'Lagoa da Conceição', 'Barra da Lagoa', 'Campeche', 'Rio Tavares', 'Ribeirão da Ilha'], 110],
        ];

        foreach ($zones as [$name, $neighborhoods, $fee]) {
            DeliveryZone::create(['name' => $name, 'neighborhoods' => $neighborhoods, 'fee' => $fee]);
        }
    }
}
