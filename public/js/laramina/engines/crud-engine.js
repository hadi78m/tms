import Ajax from '../adapters/ajax-adapter.js'
import Alert from '../adapters/alert-adapter.js'

export const CrudEngine={
 delete(url){
  Alert.confirmDelete(()=>{
   Ajax.delete(url).then(()=>Alert.success('Deleted'))
  })
 }
}