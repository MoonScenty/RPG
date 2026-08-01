<?php

namespace App\Support;

/**
 * mz_project Skills.json의 damage.formula(예: "a.atk*2-b.def", "a.mhp*0.2")를 계산한다.
 * 관찰된 전체 130개 포뮬러가 사칙연산 + a./b. 변수 참조뿐이라(괄호/함수 없음) 이 정도만
 * 지원하면 충분하지만, eval()은 쓰지 않고 소형 재귀하강 파서로 직접 해석한다.
 */
class MzFormulaEvaluator
{
    /**
     * @param  array<string, float>  $a  시전자 변수(atk/def/mat/mdf/mhp/mp 등)
     * @param  array<string, float>  $b  대상 변수
     */
    public static function evaluate(string $formula, array $a, array $b): float
    {
        $tokens = self::tokenize($formula);
        $pos = 0;
        $value = self::parseExpr($tokens, $pos, $a, $b);

        if ($pos !== count($tokens)) {
            throw new \RuntimeException("공식을 해석하지 못했습니다: {$formula}");
        }

        return $value;
    }

    /** @return array<int, string> */
    private static function tokenize(string $formula): array
    {
        preg_match_all('/\d+\.\d+|\d+|[A-Za-z_]\w*|\.|[+\-*\/()]/', $formula, $matches);

        return $matches[0];
    }

    private static function parseExpr(array $tokens, int &$pos, array $a, array $b): float
    {
        $value = self::parseTerm($tokens, $pos, $a, $b);

        while (($tokens[$pos] ?? null) === '+' || ($tokens[$pos] ?? null) === '-') {
            $op = $tokens[$pos++];
            $rhs = self::parseTerm($tokens, $pos, $a, $b);
            $value = $op === '+' ? $value + $rhs : $value - $rhs;
        }

        return $value;
    }

    private static function parseTerm(array $tokens, int &$pos, array $a, array $b): float
    {
        $value = self::parseFactor($tokens, $pos, $a, $b);

        while (($tokens[$pos] ?? null) === '*' || ($tokens[$pos] ?? null) === '/') {
            $op = $tokens[$pos++];
            $rhs = self::parseFactor($tokens, $pos, $a, $b);
            $value = $op === '*' ? $value * $rhs : $value / $rhs;
        }

        return $value;
    }

    private static function parseFactor(array $tokens, int &$pos, array $a, array $b): float
    {
        $token = $tokens[$pos] ?? null;

        if ($token === '-') {
            $pos++;

            return -self::parseFactor($tokens, $pos, $a, $b);
        }

        if ($token === '(') {
            $pos++;
            $value = self::parseExpr($tokens, $pos, $a, $b);
            if (($tokens[$pos] ?? null) !== ')') {
                throw new \RuntimeException('닫는 괄호가 없습니다.');
            }
            $pos++;

            return $value;
        }

        if ($token === 'a' || $token === 'b') {
            $pos++;
            if (($tokens[$pos] ?? null) !== '.') {
                throw new \RuntimeException("변수 뒤에 '.'이 와야 합니다: {$token}");
            }
            $pos++;
            $param = $tokens[$pos] ?? null;
            if ($param === null) {
                throw new \RuntimeException('변수 이름이 없습니다.');
            }
            $pos++;

            $vars = $token === 'a' ? $a : $b;

            return (float) ($vars[$param] ?? 0);
        }

        if ($token !== null && is_numeric($token)) {
            $pos++;

            return (float) $token;
        }

        throw new \RuntimeException('공식에 알 수 없는 토큰이 있습니다: ' . ($token ?? 'EOF'));
    }
}
