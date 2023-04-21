@foreach($datas as $data)
<tr>
    <td>{{$loop->iteration}}</td>
    <td>{{ $data->name }}</td>
    <td>{{ $data->min_items }}</td>
    <td>{{ $data->max_items }}</td>
    <td>{{ $data->weight }}</td>
    <td>{{ $data->height }} x {{ $data->width }} x {{ $data->length }}</td>
    
    <td>
        <div class="action-list">
            <a class="btn btn-secondary btn-sm "
                href="{{ route('back.box.edit', $data->id) }}">
                <i class="fas fa-edit"></i>
            </a>
            <a class="btn btn-danger btn-sm " data-toggle="modal"
                data-target="#confirm-delete" href="javascript:;"
                data-href="{{ route('back.box.destroy', $data->id) }}">
                <i class="fas fa-trash-alt"></i>
            </a>
        </div>
    </td>
</tr>
@endforeach
