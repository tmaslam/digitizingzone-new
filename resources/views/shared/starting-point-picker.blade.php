@php
    $fieldName = $name ?? 'starting_point';
    $fieldSelected = $selected ?? 'Center';
    $fieldDisabled = (bool) ($disabled ?? false);
    $fieldIdPrefix = $idPrefix ?? $fieldName;
    $startingPointLabels = $labels ?? [
        'TopLeft' => 'Top Left',
        'TopCenter' => 'Top Center',
        'TopRight' => 'Top Right',
        'MiddleLeft' => 'Middle Left',
        'Center' => 'Center',
        'MiddleRight' => 'Middle Right',
        'BottomLeft' => 'Bottom Left',
        'BottomCenter' => 'Bottom Center',
        'BottomRight' => 'Bottom Right',
    ];
    $startingPointGrid = [
        ['TopLeft', null, 'TopCenter', null, 'TopRight'],
        [null, null, null, null, null],
        ['MiddleLeft', null, 'Center', null, 'MiddleRight'],
        [null, null, null, null, null],
        ['BottomLeft', null, 'BottomCenter', null, 'BottomRight'],
    ];
@endphp

<div class="legacy-starting-point-picker" aria-label="Starting point selection">
    <table width="150" height="100" cellspacing="4" cellpadding="0" border="1" bgcolor="#D5D7D8" class="style2" style="font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 11px; padding: 0; margin: 0;" aria-label="Starting point selection">
        <tbody>
            @foreach ($startingPointGrid as $row)
                <tr>
                    @foreach ($row as $value)
                        <td width="20" height="20" align="center">
                            @if ($value)
                                <input
                                    type="radio"
                                    tabindex="15"
                                    name="{{ $fieldName }}"
                                    id="{{ $fieldIdPrefix }}_{{ $value }}"
                                    value="{{ $value }}"
                                    aria-label="{{ $startingPointLabels[$value] ?? $value }}"
                                    @checked($fieldSelected === $value)
                                    @disabled($fieldDisabled)
                                >
                            @else
                                &nbsp;
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
